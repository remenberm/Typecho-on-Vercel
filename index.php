<?php
/**
 * Typecho Blog Platform
 *
 * @copyright  Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license    GNU General Public License 2.0
 * @version    $Id: index.php 1153 2009-07-02 10:53:22Z magike.net $
 */

/* 设置PHP时区 */
date_default_timezone_set('Asia/Shanghai');

/**
 * 检测当前部署是否已经具备可用的 Typecho 安装配置。
 *
 * 对于 Vercel 的首次部署，仓库中可能已经存在 config.inc.php，但数据库环境变量尚未就绪，
 * 此时应直接进入安装流程，而不是尝试加载一个未完成配置的站点。
 *
 * @return bool
 */
function typecho_has_installed_config(): bool
{
    $configFile = dirname(__FILE__) . '/config.inc.php';

    if (!file_exists($configFile)) {
        return false;
    }

    $hasRequiredDbEnv = getenv('PGHOST') && getenv('PGUSER') && getenv('PGPASSWORD') && getenv('PGDATABASE');
    if (!$hasRequiredDbEnv) {
        return false;
    }

    try {
        require_once $configFile;

        $db = \Typecho\Db::get();
        if (empty($db)) {
            return false;
        }

        $installed = $db->fetchRow(
            $db->select()->from('table.options')->where('user = 0 AND name = ?', 'installed')
        );

        return !empty($installed['value']);
    } catch (\Throwable $e) {
        return false;
    }
}

/** 载入配置支持 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    if (!typecho_has_installed_config()) {
        file_exists('./install.php') ? header('Location: install.php') : print('Missing Config File');
        exit;
    }
}

/** 初始化组件 */
\Widget\Init::alloc();

/** 注册一个初始化插件 */
\Typecho\Plugin::factory('index.php')->begin();

/** 开始路由分发 */
\Typecho\Router::dispatch();

/** 注册一个结束插件 */
\Typecho\Plugin::factory('index.php')->end();
