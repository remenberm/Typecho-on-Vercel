# PicUp 配置示例

这个文件提供了一个可直接参考的 PicUp 配置模板，适合在 Typecho 后台的插件配置面板中粘贴。

## 结构说明

- 顶层 key 是 Profile 名称
- 每个配置项都对应一种存储驱动
- `_extensions` 用来控制压缩、WebP、加水印等扩展

## 适合先试用的示例

### 1. 本地存储（最简单）

```json
{
  "local-default": {
    "driver": "local",
    "uploadDir": "usr/uploads",
    "urlPrefix": "",
    "_extensions": {
      "compress": { "enabled": false, "quality": "82" },
      "webp": { "enabled": false, "quality": "85" },
      "watermark": { "enabled": false }
    }
  }
}
```

### 2. GitHub 仓库（你之前使用过的思路）

```json
{
  "github-cdn": {
    "driver": "github",
    "token": "",
    "repo": "owner/repo",
    "branch": "main",
    "prefix": "images",
    "cdn": "https://cdn.jsdelivr.net/gh/owner/repo",
    "_extensions": {
      "compress": { "enabled": false, "quality": "82" },
      "webp": { "enabled": false, "quality": "85" },
      "watermark": { "enabled": false }
    }
  }
}
```

### 3. S3/兼容对象存储

```json
{
  "s3-compatible": {
    "driver": "s3",
    "endpoint": "https://s3.example.com",
    "region": "us-east-1",
    "bucket": "my-bucket",
    "accessKey": "",
    "secretKey": "",
    "prefix": "images",
    "urlPrefix": "https://cdn.example.com"
  }
}
```

## 使用方法

1. 进入 Typecho 后台插件管理中的 PicUp 配置页
2. 将上面的 JSON 粘贴进去
3. 保存后选择一个 Profile 为当前激活配置
4. 重新上传即可生效

> 建议将敏感信息（token、secret）保存在环境变量或安全配置中，避免直接提交到公开仓库。
