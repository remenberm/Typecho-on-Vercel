> 简介：该项目能够实现在Vercel平台上一键部署Typecho，自动化程度较高，部署非常丝滑~

## 💡项目特色
- 0成本，完全依托于免费资源
- 一键部署，操作便捷
- 可视化操作图床，弥补了传统Typecho的缺陷

## ⌛部署步骤

### 1.拉取源码

**方法一：Vercel一键部署（推荐）**

<a href="https://vercel.com/new/clone?repository-url=https://github.com/vastroc/Typecho-on-Vercel" target="_blank" rel="noreferrer"><img src="https://vercel.com/button" alt="部署到 Vercel"></a>

**方法二：直接fork本项目**

> [!IMPORTANT]
> 拉取源码后及时将仓库设为私有，以防敏感信息泄露


### 2.创建数据库

在创建好的vercel项目中，选择`Storage - Create Database - Neon`，然后一直下一步，全部默认

### 3.再次部署Vercel

进入`Deployments - Create Deployment`

<img width="1870" height="272" alt="image" src="https://github.com/user-attachments/assets/12c30b61-237f-4485-9afa-73fccdc62dbd" />


### 4.开始安装
进入`域名/install.php`，一路下一步，非常丝滑~

最后为网站设置后台用户名和密码即可

至此，安装完毕！


## 🍬更换主题
创建一个Codespaces环境，然后将下载好的主题拖入`/usr/themes`文件夹中，提交并push，文件夹名称需和主题名称一致（即不能包含`v1.6`、`-main`等后缀），否则将无法正常使用。

<img width="407" height="390" alt="image" src="https://github.com/user-attachments/assets/21f775b6-b090-4591-930e-aca5532848a5" />


## 🖼图片上传
本项目已经将GitHub作为图床，只需要在`config.inc.php`中修改相关配置即可：

1. 首先创建一个干净的仓库，用于存储图片
2. 为此仓库[申请一个](https://github.com/settings/personal-access-tokens)Token
<img width="700" alt="image" src="https://github.com/user-attachments/assets/ee94abcf-6ba4-4ddf-9c14-f3eb4016ec13" />

3. 完善`config.inc.php`中的`GITHUB_ATTACHMENT_TOKEN`，**或者**（推荐）直接到Vercel中添加环境变量`GHTOKEN`

4. 完善`config.inc.php`中的`GITHUB_ATTACHMENT_OWNER`、`GITHUB_ATTACHMENT_REPO`，其余均可默认

> [!IMPORTANT]
> 建议将图床所在的仓库也设置为私密仓库，以防git泄露（如果误传隐私照片，即便删除后也会在git提交中留下历史记录）

> 另外强烈建议将图片仓库也部署到Vercel，并配备自定义域名，一是可以加快访问速度，二是仓库设置为私密后，无法直接通过 https://raw.githubusercontent.com/username/reponame 访问图片

## 🛠开启调试功能（选）

因为PHP对Vercel没有写入权限，无法查看到错误日志，所以代码调试很不方便

**解决方案：临时开启Typeho调试模式**

在`config.inc.php`中取消对如下代码的注释：

```
/** 开启数据库错误日志 */
define('__TYPECHO_DEBUG__', true);
```

记得及时关闭！


> 本项目代码均基于Typecho官方在GitHub的开源代码修改，本次采用版本号为[typecho 1.2.1](https://github.com/typecho/typecho/releases/tag/v1.2.1)
> 该项目参考来源于：https://fordes.dev/posts/tutorials/typecho-vercel/
