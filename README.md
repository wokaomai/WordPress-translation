# AI Translator for WooCommerce

🌐 一款基于 AI 的 WordPress/WooCommerce 多语言翻译插件，支持 Google Gemini、DeepSeek、OpenAI 和 Claude 等多个主流 AI 模型接口。翻译内容存储在数据库中，访客无需等待 API 调用即可获得即时页面加载。

---

## ✨ 核心特性

- **多 AI 模型支持** — Google Gemini / DeepSeek / OpenAI GPT / Anthropic Claude，自由切换
- **数据库缓存翻译** — 翻译结果永久存储在数据库中，访客直接读取缓存页面，零延迟
- **混合翻译模式** — 优先读取数据库缓存，无缓存时实时调用 AI 翻译并自动存储
- **国旗语言切换器** — 嵌入导航栏的下拉选择框，带各国国旗图标
- **SEO 友好 URL** — 支持 `/zh-cn/product-name/` 格式的多语言 URL + hreflang 标签
- **批量预翻译** — 后台一键批量翻译所有产品/页面/文章
- **WooCommerce 深度集成** — 产品名称、描述、简短描述自动翻译
- **自动缓存失效** — 文章/产品内容更新时自动清除对应翻译缓存
- **翻译统计面板** — 可视化查看翻译数量、按语言/模型分类统计

---

## 📦 插件结构

```
ai-translator-woocommerce/
├── ai-translator-woocommerce.php       # 插件主文件（入口）
├── includes/
│   ├── class-gemini-api.php            # Google Gemini API 接口
│   ├── class-deepseek-api.php          # DeepSeek API 接口
│   ├── class-openai-api.php            # OpenAI GPT API 接口
│   ├── class-claude-api.php            # Anthropic Claude API 接口
│   ├── class-translation-cache.php     # 数据库缓存层（双表设计）
│   ├── class-translator-core.php       # 翻译核心逻辑 & 内容过滤器
│   ├── class-admin-settings.php        # WordPress 后台设置面板
│   ├── class-ajax-handler.php          # AJAX 请求处理器
│   └── class-frontend-switcher.php     # 前端语言切换器 + SEO URL 重写
├── assets/
│   ├── css/
│   │   ├── frontend-switcher.css       # 前端下拉样式（响应式）
│   │   └── admin-settings.css          # 后台管理样式
│   ├── js/
│   │   ├── frontend-switcher.js        # 前端交互（下拉切换、Cookie）
│   │   └── admin-settings.js           # 后台交互（批量翻译、测试连接）
│   └── flags/                          # 国旗 SVG 图标
│       ├── us.svg                      # 🇺🇸 美国/英语
│       ├── cn.svg                      # 🇨🇳 中国/简体中文
│       ├── fr.svg                      # 🇫🇷 法国/法语
│       ├── es.svg                      # 🇪🇸 西班牙/西班牙语
│       └── kr.svg                      # 🇰🇷 韩国/韩语
└── readme.txt                          # WordPress 插件规范说明文件
```

---

## 🚀 安装步骤

### 方法一：手动安装

1. 下载本仓库或 clone：
   ```bash
   git clone https://github.com/wokaomai/WordPress-translation.git
   ```
2. 将 `ai-translator-woocommerce` 文件夹复制到 WordPress 的 `/wp-content/plugins/` 目录
3. 在 WordPress 后台 → 插件 → 找到 "AI Translator for WooCommerce" → 点击 **启用**

### 方法二：ZIP 上传

1. 将 `ai-translator-woocommerce` 文件夹打包为 ZIP
2. WordPress 后台 → 插件 → 安装插件 → 上传插件 → 选择 ZIP 文件

---

## ⚙️ 配置指南

### 1. 设置 AI 服务商

进入 WordPress 后台 → **AI Translator** → **Settings**

选择你偏好的 AI 服务商并填写 API Key：

| 服务商 | 获取 API Key | 推荐模型 |
|--------|-------------|----------|
| Google Gemini | [AI Studio](https://aistudio.google.com/app/apikey) | Gemini 1.5 Flash（性价比最高） |
| DeepSeek | [platform.deepseek.com](https://platform.deepseek.com/) | DeepSeek Chat（中文翻译优秀） |
| OpenAI | [platform.openai.com](https://platform.openai.com/api-keys) | GPT-4o Mini（快速低成本） |
| Claude | [console.anthropic.com](https://console.anthropic.com/) | Claude 3 Haiku（最快响应） |

点击 **Test Connection** 按钮验证 API 连接是否正常。

### 2. 选择目标语言

默认启用 5 种语言：
- 🇺🇸 English（源语言）
- 🇨🇳 简体中文
- 🇫🇷 Français
- 🇪🇸 Español
- 🇰🇷 한국어

可在设置中勾选更多语言（支持 20+ 种语言）。

### 3. 批量预翻译

进入 **AI Translator** → **Batch Translate**：

1. 选择目标语言
2. 选择内容类型（Products / Pages / Posts）
3. 设置批量大小（建议 5-10，避免 API 限流）
4. 点击 **Start Batch Translation**

> 💡 建议：先使用批量翻译预热数据库，这样访客访问时直接读取缓存，无需等待。

---

## 🔄 工作原理

```
访客请求页面 (带语言参数 ?lang=zh-cn 或 /zh-cn/ URL)
         │
         ▼
  ┌─────────────────┐
  │ 检查数据库缓存   │
  └────────┬────────┘
           │
    ┌──────┴──────┐
    │             │
  有缓存        无缓存
    │             │
    ▼             ▼
 直接返回    调用 AI API 翻译
 (< 1ms)         │
                  ▼
           存入数据库缓存
                  │
                  ▼
             返回翻译内容
```

**关键设计：**
- 翻译结果存入两张自定义表：`wp_aitwc_translations`（文本片段）和 `wp_aitwc_translation_meta`（完整文章/产品）
- 文章/产品更新时自动清除对应翻译缓存（通过 `save_post` hook）
- 长文本自动分段翻译，避免 API token 限制

---

## 🌍 SEO 支持

- **URL 重写**：`/zh-cn/product-name/`、`/fr/about-us/`
- **hreflang 标签**：自动在 `<head>` 中输出所有语言版本链接
- **x-default**：标注默认语言版本
- **Cookie 记忆**：记住用户语言偏好，下次访问自动切换

---

## 📊 数据库表结构

### `wp_aitwc_translations` — 文本片段翻译缓存

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键 |
| content_hash | VARCHAR(64) | 原文 SHA-256 哈希 |
| source_language | VARCHAR(10) | 源语言 |
| target_language | VARCHAR(10) | 目标语言 |
| original_text | LONGTEXT | 原文 |
| translated_text | LONGTEXT | 翻译文本 |
| ai_provider | VARCHAR(50) | 使用的 AI 模型 |
| post_id | BIGINT | 关联文章 ID |
| created_at | DATETIME | 创建时间 |

### `wp_aitwc_translation_meta` — 完整文章/产品翻译

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键 |
| post_id | BIGINT | WordPress 文章 ID |
| target_language | VARCHAR(10) | 目标语言 |
| translated_title | TEXT | 翻译标题 |
| translated_content | LONGTEXT | 翻译内容 |
| translated_excerpt | TEXT | 翻译摘要 |
| is_complete | TINYINT | 翻译是否完成 |

---

## 🛠️ 技术要求

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+（可选，但推荐）
- 至少一个 AI 服务商的 API Key

---

## 📋 支持的 AI 模型列表

### Google Gemini
- `gemini-pro`
- `gemini-1.5-pro`
- `gemini-1.5-flash` ⭐ 推荐（快+便宜）

### DeepSeek
- `deepseek-chat` ⭐ 推荐（中文翻译质量高）
- `deepseek-reasoner`

### OpenAI
- `gpt-4o-mini` ⭐ 推荐（性价比）
- `gpt-4o`
- `gpt-4-turbo`

### Anthropic Claude
- `claude-3-haiku-20240307` ⭐ 推荐（最快）
- `claude-3-sonnet-20240229`
- `claude-3-5-sonnet-20241022`

---

## ❓ 常见问题

**Q: 翻译会拖慢网站速度吗？**
A: 不会。翻译结果存储在数据库中，访客读取缓存和读取普通文章一样快（< 1ms）。只有第一次访问（或批量翻译时）才会调用 AI API。

**Q: 可以同时使用多个 AI 服务商吗？**
A: 可以配置所有服务商的 API Key，但同一时间只有一个活跃。你可以随时在设置中切换。

**Q: 文章更新后翻译会自动更新吗？**
A: 文章保存时会自动清除该文章的所有语言缓存。下次访客访问时会重新翻译。

**Q: API 调用费用大概多少？**
A: 取决于内容量和模型选择。以 100 篇产品为例：
- Gemini Flash: ~$0.05
- DeepSeek Chat: ~$0.02
- GPT-4o Mini: ~$0.10
- Claude Haiku: ~$0.05

---

## 📜 License

GPL v2 or later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支：`git checkout -b feature/my-feature`
3. 提交更改：`git commit -m 'Add my feature'`
4. 推送分支：`git push origin feature/my-feature`
5. 创建 Pull Request
