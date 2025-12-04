<?php
/**
 * Typecho Blog Platform
 *
 * @copyright  Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license    GNU General Public License 2.0
 * @version    $Id$
 */

/** 开启数据库错误日志 */
# define('__TYPECHO_DEBUG__', true);

/** 开启https */ 
define('__TYPECHO_SECURE__',true);

/** 定义根目录 */
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));

/** 定义插件目录(相对路径) */
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');

/** 定义模板目录(相对路径) */
define('__TYPECHO_THEME_DIR__', '/usr/themes');

/** 后台路径(相对路径) */
define('__TYPECHO_ADMIN_DIR__', '/admin/');

/** 设置包含路径 */
@set_include_path(get_include_path() . PATH_SEPARATOR .
__TYPECHO_ROOT_DIR__ . '/var' . PATH_SEPARATOR .
__TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__);

/** 载入API支持 */
require_once 'Typecho/Common.php';

/** 程序初始化 */
Typecho_Common::init();

/** 定义数据库参数 */
$db = new Typecho_Db("Pdo_Pgsql", 'typecho_');
$db->addServer(array (
  'host' => getenv('PGHOST'),
  'port' => 5432,
  'user' => getenv('PGUSER'),
  'password' => getenv('PGPASSWORD'),
  'database' => getenv('PGDATABASE'),
  'charset' => 'utf8',
), Typecho_Db::READ | Typecho_Db::WRITE);
Typecho_Db::set($db);


if (!defined('GITHUB_ATTACHMENT_TOKEN')) {
  // Fine‑grained token，确保已授权仓库并给 "Contents: Read & write"
  define('GITHUB_ATTACHMENT_TOKEN', getenv('GHTOKEN') ?: 'xxx');

  // 仓库 owner
  define('GITHUB_ATTACHMENT_OWNER', 'xxx');

  // 仓库名
  define('GITHUB_ATTACHMENT_REPO', 'xxx');

  // 分支（默认为 main）
  define('GITHUB_ATTACHMENT_BRANCH', 'main');

  // 仓库内的附件根目录（可留空或 'attachments' 等，不带首尾斜杠，支持多级目录）
  define('GITHUB_ATTACHMENT_ROOT', '');

  // 编辑器中上传图片默认目录
  define('GITHUB_EDITOR_UPLOAD_DIR', 'editor_upload');

  // 加速CDN域名(默认空即使用 GitHub 原始域名 raw.githubusercontent.com，后面不加斜杠)
  define('GITHUB_ATTACHMENT_CDN', '');

  // 最大上传大小，单位字节（默认 10MB）
  define('GITHUB_ATTACHMENT_MAX_UPLOAD_BYTES', 10 * 1024 *1024);

  // 指定 cacert.pem 路径（vercel-php默认设置）
  define('GITHUB_ATTACHMENT_CACERT_PATH', '/usr/local/etc/openssl/cacert.pem');
}