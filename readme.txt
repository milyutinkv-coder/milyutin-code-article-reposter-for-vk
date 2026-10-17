# Milyutin Code Article Reposter for VK

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-Published-green)](https://wordpress.org/plugins/milyutin-code-article-reposter-for-vk/)

> WordPress plugin that automatically publishes articles to a **VKontakte** group with AI-generated summaries via **Yandex GPT** or the first paragraph.

**Author:** Konstantin Milyutin · [milyutin-codes.ru](https://milyutin-codes.ru) · [VK Group](https://vk.com/milyutin_codes)

## ✨ Features

- 🚀 **Automatic publishing** — triggers when you publish a post.
- 🧠 **AI-generated summaries** — integrate with Yandex GPT to create engaging previews.
- 📝 **Fallback mode** — use the first paragraph (excerpt) if you don't want to pay for AI.
- 🔄 **Auto-update** — when you update a post, the plugin updates the VK wall post automatically.
- 🖱️ **Manual publishing** — "Publish now" button in the post editor.
- 📊 **Smart logging** — all actions are logged and viewable in the admin area.
- 🌍 **Fully translatable** — ready for localization.

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- VKontakte Community Access Key
- Yandex Cloud API Key (optional, for AI summaries)

## 🚀 Installation

1. Upload the plugin folder to `wp-content/plugins/` or install via the WordPress plugin repository.
2. Activate the plugin.
3. Go to **Reposter-For-VK** in the admin menu.
4. Fill in your **VK Group ID** and **Community Access Key**.
5. Choose your summary source (Yandex GPT or First Paragraph).

## 📂 File Structure

```text
milyutin-code-article-reposter-for-vk/
├── assets/
│   ├── admin.css
│   └── admin.js
├── includes/
│   ├── class-vkaire-logger.php
│   ├── class-vkaire-main.php
│   ├── class-vkaire-settings.php
│   ├── class-vkaire-vk-poster.php
│   └── class-vkaire-yandex-gpt.php
├── index.php
├── milyutin-code-article-reposter-for-vk.php
└── readme.txt
