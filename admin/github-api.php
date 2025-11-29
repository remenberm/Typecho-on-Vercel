<?php
include_once 'common.php';
if (!$user->pass('editor', true) && !$user->pass('administrator', true)) gh_error('权限不足');

include 'github-helpers.php';
if (!gh_is_configured()) gh_error('GitHub 未配置，请到 config.inc.php 中进行配置');
$cfg = gh_cfg_raw();
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action === 'list_all') {
    $branch = $cfg['branch'] ?: 'main';
    // 获取目录树
    $res = gh_api_raw_request('GET', "git/trees/".rawurlencode($branch)."?recursive=1", null);
    if ($res['status'] !== 200) {
        gh_error('GitHub git/trees API 错误: ' . ($res['body']['message'] ?? $res['status']));
    }

    $tree = $res['body']['tree'] ?? [];
    $images = [];
    $dirs = [];
    $imageExt = ['png','jpg','jpeg','gif','webp','svg','bmp','ico','tiff','heic'];

    foreach ($tree as $node) {
        // 筛选文件：有path 且 节点类型是blob
        if (!isset($node['path']) || ($node['type'] ?? '') !== 'blob') continue;
        // 原始路径（从仓库最上级目录开始）
        $full_path = $node['path'];
        // 如果设置了根目录，展示路径要去掉根目录
        if (!empty($cfg['root'])) {
            if (strpos($full_path, $cfg['root'] . '/') !== 0) continue;
            $displayPath = substr($full_path, strlen($cfg['root']) + 1);
        } else {
            // 如果没有设置根目录，展示路径就等于原始路径
            $displayPath = $full_path;
        }

        $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
        if (!in_array($ext, $imageExt)) continue;

        $name = basename($full_path);
        // 拆分斜杠的各部分，进行url编码，再合并
        $rawUrl = "{$cfg['cdn']}/" . implode('/', array_map('rawurlencode', explode('/', $full_path)));

        $size = isset($node['size']) ? intval($node['size']) : null;
        $images[] = ['path' => $displayPath, 'full_path' => $full_path, 'name' => $name, 'url' => $rawUrl, 'size' => $size];

        // 提取目录
        $parts = explode('/', $displayPath);
        // 是否多级目录
        if (count($parts) > 1) {
            // 移除文件名部分
            array_pop($parts);
            $prefix = '';
            // 拼接目录片段，形成多个多级目录（包含子目录）
            foreach ($parts as $p) {
                $prefix = $prefix === '' ? $p : ($prefix . '/' . $p);
                if (!in_array($prefix, $dirs)) $dirs[] = $prefix;
            }
        }
    }

    // 文件和目录排序
    // usort($images, function($a,$b){ return -strcmp(strtolower($a['name']), strtolower($b['name'])); });
    // 构建正则：匹配 20xxxx-xxxxxx.指定后缀（全匹配，忽略大小写）
    // 正则拆解：^20\d{6}-\d{6}\.(png|jpg|...)i$
    $extStr = implode('|', $imageExt); // 转成 "png|jpg|jpeg|..."
    $pattern = "/^20\d{6}-\d{6}\.($extStr)$/i"; // i：忽略大小写，^$：全匹配（避免部分匹配）

    usort($images, function($a, $b) use ($pattern) {
        // 1. 判断两个文件名是否符合「20xxxx-xxxxxx.图片后缀」规则
        $aMatches = preg_match($pattern, $a['name']); // 符合返回1，不符合返回0
        $bMatches = preg_match($pattern, $b['name']);
        
        // 2. 第一优先级：符合规则的排在前面，不符合的排在后面
        if ($aMatches != $bMatches) {
            return $bMatches - $aMatches; // 1（符合）在前，0（不符合）在后
        }
        
        // 3. 第二优先级：同一组内（都符合/都不符合），按文件名降序排列（保留原逻辑）
        return -strcmp(strtolower($a['name']), strtolower($b['name']));
    });
    sort($dirs, SORT_STRING);

    gh_ok(['images'=>$images, 'dirs'=>$dirs]);
}

if ($action === 'upload') {
    if (empty($_FILES['file'])) gh_error('没有发现上传文件');
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) gh_error('文件上传错误: ' . $file['error']);

    // 文件大小检查
    $maxSize = $cfg['max_bytes'];
    if (!empty($file['size']) && $file['size'] > $maxSize) {
        gh_error('上传文件过大（最大 ' . round($maxSize/1024/1024,2) . ' MB）');
    }
    // 安全检查
    if (!is_uploaded_file($file['tmp_name'])) {
        gh_error('非法上传请求');
    }
    // 如果请求来自editor，则默认上传至默认文件夹
    if(isset($_POST['isFromEditor']) && trim($_POST['isFromEditor']) === '1') {
        $path = $cfg['editor_upload_dir'];
    } else {
        $path = isset($_POST['path']) ? trim((string)$_POST['path'], '/') : '';
    }

    $apiDir = gh_path_normalize($path);
    
    date_default_timezone_set('Asia/Shanghai'); // 中国标准时间（CST，UTC+8）
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    // 生成格式为 20250101-120003 的时间字符串
    $timeStr = date('Ymd-His');
    // 拼接扩展名（有扩展名则加 .ext，无则直接用时间字符串）
    $filename = $ext ? $timeStr . '.' . $ext : $timeStr;

    $target = ($apiDir === '') ? $filename : ($apiDir . '/' . $filename);

    $content = @file_get_contents($file['tmp_name']);
    if ($content === false) gh_error('无法读取上传临时文件');

    $b64 = base64_encode($content);
    $body = [
        'message' => "Upload: {$filename}",
        'content' => $b64,
        'branch'  => gh_cfg_raw()['branch']
    ];

    // 发起 PUT 请求
    $res = gh_api_request('PUT', $target, $body);

    if (isset($res['status']) && ($res['status'] === 201 || $res['status'] === 200)) {
        $rawUrl = "{$cfg['cdn']}/" . implode('/', array_map('rawurlencode', explode('/', $target)));
        gh_ok(['message' => '上传成功', 'file' => $target, 'url' => $rawUrl]);
    } else {
        // 将 GitHub 返回的 message 或 status 返回给前端
        $msg = $res['body']['message'] ?? ($res['error'] ?? ($res['status'] ?? '未知错误'));
        gh_error('上传失败: ' . $msg);
    }
}

if ($action === 'move') {
    $src = isset($_POST['src']) ? trim((string)$_POST['src'], '/') : (isset($_GET['src'])?trim((string)$_GET['src'],'/'):'');
    $dst = isset($_POST['dst']) ? trim((string)$_POST['dst'], '/') : (isset($_GET['dst'])?trim((string)$_GET['dst'],'/'):'');
    if ($src === '' || $dst === '') gh_error('请指定 src 与 dst');

    $srcTarget = gh_path_normalize($src);
    $dstTarget = gh_path_normalize($dst);

    if (strpos($srcTarget, '..') !== false || strpos($dstTarget, '..') !== false) gh_error('非法路径参数');

    // 先通过 Contents API 读取源文件元信息与可能的 content
    $get = gh_api_request('GET', $srcTarget, null);
    if (!isset($get['status']) || $get['status'] !== 200) {
        gh_error('无法读取源文件');
    }

    // 尝试从 contents API 取得 content（通常小文件有），否则回退到 git/blobs/:sha 获取 base64 内容（适用于大文件）
    $content_b64 = null;
    $sha = isset($get['body']['sha']) ? $get['body']['sha'] : null;

    if (isset($get['body']['content']) && $get['body']['content'] !== '') {
        $content_b64 = $get['body']['content'];
    } elseif ($sha) {
        // 回退到 Git Data API 获取 blob（base64 编码）
        $blobRes = gh_api_raw_request('GET', 'git/blobs/' . rawurlencode($sha), null);
        if (isset($blobRes['status']) && $blobRes['status'] === 200 && isset($blobRes['body']['content'])) {
            $content_b64 = $blobRes['body']['content'];
        } else {
            gh_error('无法读取源文件内容（blob 获取失败）');
        }
    } else {
        gh_error('无法读取源文件内容');
    }

    // content_b64 是 base64 字符串（与原来对小文件的处理一致）
    $content_b64 = (string)$content_b64;

    $createBody = ['message' => "Move: {$src} -> {$dst}", 'content' => $content_b64, 'branch' => gh_cfg_raw()['branch']];
    $create = gh_api_request('PUT', $dstTarget, $createBody);
    if (!in_array($create['status'], [200,201])) gh_error('复制到目标失败: ' . ($create['body']['message'] ?? $create['status']));

    // 删除源：仍然使用最初通过 contents API 获取到的 sha（如果通过 blob 获取也保留 sha）
    if (!$sha && isset($get['body']['sha'])) $sha = $get['body']['sha'];
    if (!isset($sha) || !$sha) {
        gh_error('无法获取源文件 sha，无法删除源文件');
    }

    $del = gh_api_request('DELETE', $srcTarget, ['message' => "Remove after move: {$src}", 'sha' => $sha, 'branch' => gh_cfg_raw()['branch']]);
    if ($del['status'] === 200) gh_ok(['message' => '移动成功']);
    else gh_error('移动后删除源失败: ' . ($del['body']['message'] ?? $del['status']));
}

if ($action === 'delete') {
    // 接收路径（优先 POST）
    $path = isset($_POST['path']) ? $_POST['path'] : (isset($_GET['path']) ? $_GET['path'] : '');
    if ($path === '') gh_error('请指定要删除的文件路径');

    $target = gh_path_normalize($path);
    
    // path sanity
    if (strpos($target, '..') !== false) gh_error('非法路径参数');
    
    // 先尝试读取文件以获取 sha（必需用于删除）
    $check = gh_api_request('GET', $target, null);
    // 如果没有找到 sha，尝试切换带 root / 不带 root 的另一种形式（容错）
    if (!isset($check['status']) || $check['status'] !== 200 || !isset($check['body']['sha'])) {
        $root = rtrim($cfg['root'], '/');
        if ($root !== '') {
            if (stripos($target, $root . '/') === 0) $try = substr($target, strlen($root) + 1);
            else $try = $root . '/' . ltrim($target, '/');
            $check2 = gh_api_request('GET', $try, null);
            if (isset($check2['status']) && $check2['status'] === 200 && isset($check2['body']['sha'])) { $check = $check2; $target = $try; }
        }
    }

    if (!isset($check['status']) || $check['status'] !== 200 || !isset($check['body']['sha'])) {
        gh_error('目标文件不存在或无法获取 sha（' . htmlspecialchars($target) . '）');
    }

    $sha = $check['body']['sha'];
    $body = ['message' => "Delete: {$path}", 'sha' => $sha, 'branch' => gh_cfg_raw()['branch']];
    // 调用 GitHub Contents API 删除
    $res = gh_api_request('DELETE', $target, $body);
    if (isset($res['status']) && $res['status'] === 200) gh_ok(['message' => '删除成功']);
    else { $msg = $res['body']['message'] ?? ($res['error'] ?? ($res['status'] ?? 'Unknown')); gh_error('删除失败: ' . $msg); }
}