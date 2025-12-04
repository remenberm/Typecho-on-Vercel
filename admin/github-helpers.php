<?php
include_once 'common.php';
if (!$user->pass('editor', true) && !$user->pass('administrator', true)) gh_error('权限不足');

/* ----------------- Helpers / Config ----------------- */

// 获取配置信息
if (!function_exists('gh_cfg_raw')) {
    function gh_cfg_raw() {
        $owner  = defined('GITHUB_ATTACHMENT_OWNER') ? GITHUB_ATTACHMENT_OWNER : '';
        $repo   = defined('GITHUB_ATTACHMENT_REPO') ? GITHUB_ATTACHMENT_REPO : '';
        $branch = defined('GITHUB_ATTACHMENT_BRANCH') ? GITHUB_ATTACHMENT_BRANCH : 'main';
        return [
            'token'  => defined('GITHUB_ATTACHMENT_TOKEN') ? GITHUB_ATTACHMENT_TOKEN : '',
            'owner'  => defined('GITHUB_ATTACHMENT_OWNER') ? GITHUB_ATTACHMENT_OWNER : '',
            'repo'   => defined('GITHUB_ATTACHMENT_REPO') ? GITHUB_ATTACHMENT_REPO : '',
            'branch' => defined('GITHUB_ATTACHMENT_BRANCH') ? GITHUB_ATTACHMENT_BRANCH : 'main',
            'root'   => defined('GITHUB_ATTACHMENT_ROOT') ? trim(GITHUB_ATTACHMENT_ROOT, "/") : '',
            'editor_upload_dir' => defined('GITHUB_EDITOR_UPLOAD_DIR') ? trim(GITHUB_EDITOR_UPLOAD_DIR, "/") : '',
            'cdn'    => defined('GITHUB_ATTACHMENT_CDN') ? rtrim(GITHUB_ATTACHMENT_CDN, '/') : 'https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}',
            'max_bytes' => defined('GITHUB_ATTACHMENT_MAX_UPLOAD_BYTES') ? (int)GITHUB_ATTACHMENT_MAX_UPLOAD_BYTES : 10 * 1024 * 1024,
        ];
    }
}

// 检查是否已配置
if (!function_exists('gh_is_configured')) {
    function gh_is_configured() {
        $c = gh_cfg_raw();
        return (!empty($c['token']) && !empty($c['owner']) && !empty($c['repo']));
    }
}

// error 输出 JSON 并 exit
if (!function_exists('gh_error')) {
    function gh_error($msg) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => (string)$msg]);
        exit;
    }
}

// ok 输出 JSON 并 exit
if (!function_exists('gh_ok')) {
    function gh_ok($data = []) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => true], (array)$data));
        exit;
    }
}

// 路径规范化（root/path）
if (!function_exists('gh_path_normalize')) {
    function gh_path_normalize($path) {
        $path = trim($path, '/');
        $parts = explode('/', $path);
        $encodedParts = [];
        foreach ($parts as $part) {
            // URL 编码（符合 GitHub API 要求）
            $encodedParts[] = rawurlencode($part);
        }

        $cfg = gh_cfg_raw();
        $root = $cfg['root'];
        $rootParts = [];
        if (!empty($root)) {
            $rootParts = explode('/', trim($root, '/'));
        }
        foreach ($rootParts as $rootPart) {
            $basePartsEncoded[] = rawurlencode($rootPart);
        }

        $fullEncodedParts = array_merge($basePartsEncoded ?? [], $encodedParts);
        $fullPath = trim(implode('/', $fullEncodedParts), '/');
        $fullPath = preg_replace('#/\.\./#', '/', $fullPath);
        return $fullPath;
    }
}

// HTTP helper to GitHub API (raw endpoints)
if (!function_exists('gh_api_raw_request')) {
    function gh_api_raw_request($method, $endpoint, $body = null, $isJson = true) {
        $cfg = gh_cfg_raw();
        if (empty($cfg['owner']) || empty($cfg['repo'])) {
            return ['status' => 0, 'error' => 'GITHUB configuration missing'];
        }
        $url = "https://api.github.com/repos/{$cfg['owner']}/{$cfg['repo']}/{$endpoint}";
        $ch = curl_init($url);

        $headers = [
            'Accept: application/vnd.github.v3+json',
            'User-Agent: Typecho-GitHub-Attachment'
        ];
        if (!empty($cfg['token'])) {
            $headers[] = 'Authorization: token ' . $cfg['token'];
        }
        if ($isJson) $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        // 避免长时间挂起
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        if ($body !== null) {
            $payload = $isJson ? json_encode($body) : $body;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        // CA 检测与设置（与原实现保持一致）
        $ca = '';
        // 如果config.inc.php中定义了CA证书路径且有效
        if (defined('GITHUB_ATTACHMENT_CACERT_PATH') && GITHUB_ATTACHMENT_CACERT_PATH) {
            $p = GITHUB_ATTACHMENT_CACERT_PATH;
            if (file_exists($p) && is_readable($p)) $ca = $p;
        }
        // 如果没有，则尝试从php.ini中读取curl.cainfo或openssl.cafile
        if (!$ca) {
            $cainfo = ini_get('curl.cainfo');
            if ($cainfo && file_exists($cainfo) && is_readable($cainfo)) $ca = $cainfo;
        }
        // 如果仍没有，则尝试openssl.cafile
        if (!$ca) {
            $openssl = ini_get('openssl.cafile');
            if ($openssl && file_exists($openssl) && is_readable($openssl)) $ca = $openssl;
        }
        // 最后尝试一些常见路径
        // $paths = ['C:\\php\\extras\\ssl\\cacert.pem','C:\\php\\cacert.pem','/etc/ssl/certs/ca-certificates.crt','/etc/pki/tls/certs/ca-bundle.crt'];
        // if (!$ca) {
        //     foreach ($paths as $pp) { if (file_exists($pp) && is_readable($pp)) { $ca = $pp; break; } }
        // }
        if ($ca) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            // fallback (only when no CA available)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);

        curl_close($ch);

        if ($resp === false) return ['status'=>0,'error'=>$err];
        $json = json_decode($resp, true);
        return ['status'=>$status,'body'=>$json,'raw'=>$resp];
    }
}

if (!function_exists('gh_api_request')) {
    function gh_api_request($method, $apiPath, $body = null, $isJson = true) {
        $apiPath = ltrim($apiPath, '/');
        $branch = gh_cfg_raw()['branch'] ?: 'main';
        $endpoint = "contents/{$apiPath}";
        if ($branch) $endpoint .= '?ref=' . rawurlencode($branch);
        return gh_api_raw_request($method, $endpoint, $body, $isJson);
    }
}