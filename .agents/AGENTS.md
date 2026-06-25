# 防御性编程与接口重构安全协议 (Defensive Programming & Refactoring Protocol)

为了彻底杜绝在庞大的 PHP/JS 单体架构重构中由于拼写错误、漏括号、语法错误或滥用 Git 等原因导致的代码丢失和系统崩溃，在后续所有的模块改造（包括 177 个接口）中，必须强制执行以下 SOP：

1. **绝对禁用 Git 危险指令**：在任何情况下，禁止使用 `git restore`、`git checkout` 或 `git reset` 撤销代码。所有回滚操作必须且只能通过物理覆盖（从 `Development_Backup` 中恢复备份文件）来实现，以保护所有未提交的代码资产。
2. **强制执行前端 AST 语法静态检测**：在修改任何包含 JavaScript 代码的 PHP 文件（如 `class-o100-loyalty-proxy-admin.php`）后，严禁靠人工肉眼检查。必须使用自动化脚本将文件中的 `<script>` 标签内容提取出来，剥离 PHP 标签，并在后台隐式执行 `node --check` 语法分析。只有 100% 通过无报错，才能继续后续操作并向用户汇报。
3. **强制方法寻址嗅探 (Method Discovery)**：在编写 JS 或 PHP 代码调用某个对象的方法（如 `this.xxx()`）之前，必须强制使用 `grep_search` 在上下文中定位该方法的真实签名和名称（如搜寻 `xxx: function`）。绝对禁止依靠直觉或“幻觉”捏造、盲写函数名。
4. **禁止大规模代码块整体替换**：禁止使用 `multi_replace_file_content` 一次性圈定数十行带有大量控制流嵌套（如 if、for、promise 链）的代码块进行粗暴覆盖，以防丢失闭合大括号。所有代码替换必须采用“外科手术式”（Surgical Replacement），将颗粒度缩小到仅替换发生实际逻辑变化的特定行。
5. **遗留 AJAX 接口桥接模式 (Graceful Degradation)**：在对旧版 `wp_ajax_` 接口进行 REST API 重构时，严禁直接删毁原 PHP 函数。必须采用双轨制，新的 REST Endpoint 需通过桥接调用或 `ob_start()` 捕获原业务逻辑。直到前端组件 100% 在新 REST 接口下平稳运行并经用户验证后，方可启动废弃清理程序。
6. **单点突破，严禁批量盲改**：所有 177 个接口必须划分 Phase 进行单独改造。改完一个，验证一个。只有在获得用户明确的“验证通过”反馈且自动备份到 `Development_Backup` 之后，才允许开启下一个接口的重构。
- **严禁甩锅缓存**：严禁将问题归咎于浏览器缓存、OPcache 或任何形式的 Cache。用户每次测试必然是硬刷新。如果用户反馈无效，必须承认是代码逻辑本身存在盲区或 Bug，绝不能靠猜测去反复试错。必须严格执行诊断优先原则，编写诊断脚本定位根源。

7. **强制 UI 组件标准化 (Strict UI Standardization)**：在进行任何涉及前端结构（后台面板、表单、输入框、按钮、表格等）的代码编写或修改前，必须优先阅读并严格遵照 `order100-ui-standards` 技能包 (`.agents/skills/order100-ui-standards/examples/` 目录) 中的标准代码片段。绝不使用纯原生 `<select>`，带单位的组合输入框必须使用预设的 Inline Styles（消除 margin、补齐圆角）来抵御 WordPress 样式污染，严禁主观随意发挥。

# 标准化 UI 与交互规范 (Standard UI Component Guidelines)
- **前端分页与过滤 (Pagination & Filters)**：
  - 在构建标准的后台列表、筛选工具栏（Toolbar）以及分页（Pagination）组件时，**必须**严格参考现有标准组件（如 `core/menu-maker/views/tab-items.php`）的 DOM 结构和交互模式，绝对不允许凭空想象重新实现。
  - **防止刷新 Bug (Form Submission Prevention)**：由于 WordPress 后台原生的不可见表单包裹，在手写原生 `<button>` 元素作为前端交互按钮（如“上一页”、“下一页”、“刷新”等）时，必须**永远强制显式添加 `type="button"` 属性**，并且在 Vanilla JS 的 `onclick` 中加上 `return false;`，以彻底阻断浏览器默认的触发表单提交（整页刷新）行为。
  - **列表数据的无刷新更新 (Seamless Table Update)**：使用 AJAX (Fetch) 翻页或者拉取数据时，**绝对禁止**在请求发出瞬间使用如 `tbody.innerHTML = 'Loading...'` 这种会导致 DOM 结构瞬间崩塌再重建的破坏性代码，它会引发刺眼的屏幕闪烁效果。应采用无刷新更新体验，例如在旧数据之上设置 `opacity: 0.5` 与 `pointer-events: none;` 进入加载状态，直到新数据返回后瞬间替换内容并恢复透明度。

# 数据库与 SQL 操作安全准则 (Database Query Safety Guidelines)
- **多表关联防崩塌机制 (JOIN Safety & Verification)**：
  - 编写复杂 SQL 的 JOIN 语句前（比如试图将某个统计字段拉出），**绝对禁止仅靠经验或猜测**直接关联历史数据库表（如 `o100_loyalty_transactions`）和字段。
  - 修改 SQL 前必须先用工具审查（或阅读 Schema 源码）确认两张表是否存在稳定可靠的关联键（Join Key），例如确认 `reference_id` 的类型和具体存放数据，避免在代码中使用不存在的 `promo_id` 列导致整个页面（后台接口）因为 500 DB Error 瞬间崩溃白屏。
  - 若无法通过单纯的外键关联，应使用代码级别的合并或根据 JSON 条件进行匹配。

# Vanilla JS 操作与批量替换防重踩雷 (Script Replacement Caveats)
- **精准代码替换 (Avoid Brittle Script Replacement)**：在对庞大的 HTML/JS 混合文件（如 `proxy-main.php`）进行代码覆盖或分页替换时，若使用基于字符串匹配的脚本工具，必须事先用 `view_file` 或 `grep_search` **精确确认旧代码的关键变量名称**（比如确认 `innerText` 与 `textContent` 的实际使用情况，确认 HTML 注释文字的具体排版），不要因为 1 个单词的不一致导致脚本静默失败、旧逻辑依然执行并引发 `null` 错误。
