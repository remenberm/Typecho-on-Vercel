<?php
if (!defined('__DIR__')) {
    define('__DIR__', dirname(__FILE__));
}

define('__TYPECHO_ADMIN__', true);

/** 载入配置文件 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $configFile = __DIR__ . '/../config.inc.php';

    if (file_exists($configFile)) {
        try {
            require_once $configFile;

            $db = \Typecho\Db::get();
            $installed = $db->fetchRow(
                $db->select()->from('table.options')->where('user = 0 AND name = ?', 'installed')
            );

            if (empty($installed['value'])) {
                throw new \Exception('not installed');
            }
        } catch (\Throwable $e) {
            file_exists(__DIR__ . '/../install.php') ? header('Location: ../install.php') : print('Missing Config File');
            exit;
        }
    } else {
        file_exists(__DIR__ . '/../install.php') ? header('Location: ../install.php') : print('Missing Config File');
        exit;
    }
}

/** 初始化组件 */
\Widget\Init::alloc();

/** 注册一个初始化插件 */
\Typecho\Plugin::factory('admin/common.php')->begin();

\Widget\Options::alloc()->to($options);
\Widget\User::alloc()->to($user);
\Widget\Security::alloc()->to($security);
\Widget\Menu::alloc()->to($menu);

/** 初始化上下文 */
$request = $options->request;
$response = $options->response;

/** 检测是否是第一次登录 */
$currentMenu = $menu->getCurrentMenu();

if (!empty($currentMenu)) {
    $params = parse_url($currentMenu[2]);
    $adminFile = basename($params['path']);

    if (!$user->logged && !\Typecho\Cookie::get('__typecho_first_run')) {
        if ('welcome.php' != $adminFile) {
            $response->redirect(\Typecho\Common::url('welcome.php', $options->adminUrl));
        } else {
            \Typecho\Cookie::set('__typecho_first_run', 1);
        }
    } elseif ($user->pass('administrator', true)) {
        /** 检测版本是否升级 */
        $mustUpgrade = version_compare(\Typecho\Common::VERSION, $options->version, '>');

        if ($mustUpgrade && 'upgrade.php' != $adminFile && 'backup.php' != $adminFile) {
            $response->redirect(\Typecho\Common::url('upgrade.php', $options->adminUrl));
        } elseif (!$mustUpgrade && 'upgrade.php' == $adminFile) {
            $response->redirect($options->adminUrl);
        } elseif (!$mustUpgrade && 'welcome.php' == $adminFile && $user->logged) {
            $response->redirect($options->adminUrl);
        }
    }
}
