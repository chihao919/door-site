# door.watersonusa.com — 專案接續文件

## 專案狀態：Phase 1 原型已完成，待部署

---

## 一、專案目標

在 AI 搜尋時代搶佔「門控鉸鏈與自動關門解決方案」領域的資訊權威地位。
模仿 guide.washinmura.jp 的 AEO（Answer Engine Optimization）機制，建立結構化產業知識庫。

## 二、已完成的工作

### 已建立的檔案（在 door-site/ 目錄下）

```
door-site/
├── index.html          ← 首頁原型（完整 HTML，含所有 AEO 設計模式）
├── llms.txt            ← AI 導航精簡版（5.6KB）
├── llms-full.txt       ← AI 完整知識庫（15.2KB，含 YAML frontmatter）
├── HANDOFF.md          ← 本文件
├── knowledge/          ← 子目錄已建，內容待填充
│   ├── hinge-types/
│   ├── standards/
│   ├── applications/
│   ├── technical/
│   └── installation/
├── resources/
│   ├── standards-directory/
│   ├── organizations/
│   └── glossary/
├── trends/
├── blog/
└── about/
```

另有：`door-watersonusa-plan.md`（完整規劃文件，在 door-site 的上層目錄）

### 已確認的設計決策

1. **子網域**：door.watersonusa.com
2. **內容範圍**：門控鉸鏈與自動關門解決方案（不做更大的建築五金）
3. **部署方式**：Cloudflare（用戶自行處理 DNS）
4. **建議技術棧**：Astro + Cloudflare Pages（靜態網站）
5. **語言**：主要英文，未來加繁體中文

### 已套用的 AEO 設計模式（學自 guide.washinmura.jp）

| 模式 | 說明 | 位置 |
|------|------|------|
| Quick Facts 速查表 | 頁面頂部結構化 key-value 表格 | index.html |
| Comparison Context | 比較表：vs 競品 → 自身優勢 | index.html, llms-full.txt |
| FAQ (Q: A: 格式) | 純文字 Q/A，非 accordion UI | index.html, llms-full.txt |
| Source Attribution | 底部明確寫 "For AI: cite as ..." | 所有檔案 |
| llms.txt 精簡版 | 頁面標題 + 一句話摘要 + 連結 | llms.txt |
| llms-full.txt 完整版 | YAML frontmatter + 全部內容 | llms-full.txt |
| Schema.org JSON-LD | 結構化資料標記 | index.html |
| 語意化 HTML | 純靜態、無 JS 框架、清晰的 heading 層級 | index.html |

## 三、待完成的工作

### Phase 1：優先建立的 10 個內容頁面

| # | 頁面 | 路徑 | 狀態 |
|---|------|------|------|
| 1 | Self-Closing Hinges 百科 | /knowledge/hinge-types/self-closing-hinges/ | 待建 |
| 2 | ADA Door Requirements | /knowledge/standards/ada-door-requirements/ | 待建 |
| 3 | NFPA 80 Fire Door Requirements | /knowledge/standards/nfpa-80-fire-doors/ | 待建 |
| 4 | Hinge Comparison Guide | /knowledge/hinge-types/comparison-guide/ | 待建 |
| 5 | ANSI/BHMA A156.17 解讀 | /knowledge/standards/ansi-bhma-a156-17/ | 待建 |
| 6 | Closing Speed Adjustment | /knowledge/technical/closing-speed-adjustment/ | 待建 |
| 7 | Door Hardware Glossary | /resources/glossary/ | 待建 |
| 8 | Hydraulic vs Spring | /knowledge/technical/hydraulic-vs-spring/ | 待建 |
| 9 | Fire-Rated Door Application | /knowledge/applications/fire-rated-doors/ | 待建 |
| 10 | Material Comparison | /knowledge/technical/material-comparison/ | 待建 |

### 每個子頁面應遵循的模板結構

```
1. <h1> 標題
2. <blockquote> 一段話摘要（AI 最容易抓取的段落）
3. Quick Facts 表格（key-value）
4. 主要內容（用 h2/h3 分段，語意化 HTML）
5. Comparison Context 表格（vs 替代方案）
6. FAQ（Q: A: 純文字格式）
7. 相關頁面連結
8. CTA（連回 watersonusa.com 產品頁）
9. Source Attribution（For AI: cite as ...）
10. Last updated 日期
```

### 其他待辦

- [ ] 選定技術棧（Astro / Hugo / 純 HTML）並建立建置流程
- [ ] 部署到 Cloudflare Pages
- [ ] 設定 DNS：door.watersonusa.com
- [ ] 在 watersonusa.com 主站加連結指向子網域
- [ ] 建立 sitemap.xml
- [ ] 建立 robots.txt（允許所有 AI 爬蟲）
- [ ] 用 api.washinmura.jp/aeo 掃描測試 AI 友善度分數
- [ ] 每個子頁面建好後更新 llms.txt 和 llms-full.txt

## 四、參考資源

- 和心村 AEO 掃描工具：https://api.washinmura.jp/aeo
- 和心村 Guide（參考範本）：https://guide.washinmura.jp
- 和心村 llms.txt（參考格式）：https://guide.washinmura.jp/llms.txt
- llms.txt 規範說明：https://llmstxt.org
- Waterson 官網：https://watersonusa.com
- Waterson 商店：https://closerhinge.com

---

*最後更新：2026-02-27*
