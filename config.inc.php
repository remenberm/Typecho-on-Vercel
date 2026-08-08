<?php
// site root path
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));

// plugin directory (relative path)
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');

// theme directory (relative path)
define('__TYPECHO_THEME_DIR__', '/usr/themes');

// admin directory (relative path)
define('__TYPECHO_ADMIN_DIR__', '/admin/');

// register autoload
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

// init
\Typecho\Common::init();

// config db
$db = new \Typecho\Db('Pdo_SQLite', 'typecho_');
$db->addServer(array (
  'file' => '/workspaces/Typecho-on-Vercel/usr/6a775fa28b6ad.db',
), \Typecho\Db::READ | \Typecho\Db::WRITE);
\Typecho\Db::set($db);
