> 简介：该项目能够实现在Vercel平台上一键部署Typecho，自动化程度较高，部署非常丝滑~

## 💡项目简介
Typecho on Vercel 是一个让你能够在 Vercel 平台上零成本部署 Typecho 博客系统的项目。基于 Typecho 官方开源代码修改适配，完美兼容 Vercel 的无服务器环境。

## 💡项目特色
- 💰 0 成本：完全依托于 Vercel 的免费资源
- 🚀 一键部署：操作便捷，快速上线-
- ⚡ 保留官方能力：保留 Typecho 官方基础功能，并适配 Vercel 部署环境-
- 🔄 自动同步：通过 GitHub Actions 定时拉取更新

## ⌛部署步骤

### 1.拉取源码

**方法一：Vercel一键部署（推荐）**

<a href="https://vercel.com/new/clone?repository-url=https://github.com/vastroc/Typecho-on-Vercel" target="_blank" rel="noreferrer"><img src="https://vercel.com/button" alt="部署到 Vercel"></a>

**方法二：直接fork本项目**
> [!IMPORTANT]
> 在 Vercel 中导入该仓库
> 拉取源码后请及时将仓库设为私有，以防敏感信息泄露


### 2.创建数据库
在创建好的vercel项目中，选择`Storage - Create Database - Neon`，然后一直下一步，全部默认
💡 Neon 是 Vercel 官方推荐的 PostgreSQL 数据库服务，提供免费额度，非常适合本项目。

### 3.再次部署Vercel
进入 Vercel 项目面板的 Deployments → Create Deployment，重新部署一次以使数据库配置生效
进入`Deployments - Create Deployment`

### 4.开始安装
访问 你的域名/install.php，按照安装向导一路下一步即可-1。最后为网站设置后台用户名和密码，安装完成！🎉

## 🖼图片上传
本项目使用https://github.com/lhl77/Typecho-Plugin-PicUp

使用教程请参考此项目简介<a href="https://blog.lhl.one/artical/1026.html">PicUp中文文档</a> ·

## 注意
Vercel 的生产环境是基于无服务器 (Serverless) 架构的，其文件系统本质上是只读的（Read-Only File System）。使用过程中可能出现文件权限问题，请自行探索。
