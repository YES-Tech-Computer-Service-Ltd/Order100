# Order100 模块改造与 REST API 迁移交接文档 (Handover Document)

**最后更新**: 2026-07-01
**当前所处阶段**: REST API Phase 1 后期 & Reservation 模块收尾

---

## 1. 核心目标回顾 (Project Goals)

- **首要优先目标 (Primary Goal)**：将历史遗留的所有 `wp_ajax_` 方式的旧版交互，彻底重构并迁移到标准的 WordPress REST API (`/wp-json/o100/v1/`)。
- **次要目标 (Secondary Goal)**：在接口改造的同时，完成各个功能模块（如 Loyalty, Promo, Menu, Reservation）的前端 Vue/Alpine 架构解耦，实现全面优化与 FluentCRM 风格的现代化 UI 整合。

## 2. AGENTS.md 防御性编程与整改核心要求 (Global Directives)

为了避免在庞大单体架构重构中因低级失误导致系统崩溃，已在全局严格确立以下必须执行的 SOP：
1. **绝对禁用 Git 危险指令**：严禁 `git restore/reset`，所有回滚只能通过 `Development_Backup` 物理文件夹覆盖。
2. **强制 AST 静态检测**：修改含 JS 代码的 PHP 文件后，必须提取 `<script>` 并在后台隐式执行 `node --check` 语法分析，100% 无报错方可继续。
3. **强制方法寻址嗅探 (Method Discovery)**：调用任何对象方法前，必须使用 `grep_search` 确认真实签名，严禁依靠直觉盲写或“幻觉”捏造。
4. **禁止大规模代码块粗暴替换**：严禁一次性圈定数十行带有控制流的代码进行覆盖。必须采取“外科手术式” (Surgical Replacement) 的精准行级替换，防范括号丢失。
5. **遗留 AJAX 接口桥接模式**：重构 REST 接口时，必须先双轨制兼容原业务逻辑 (Graceful Degradation)。新接口 100% 稳定运行前，严禁直接删毁原 PHP 函数。
6. **单点突破，逐个击破**：177 个接口必须分 Phase 进行，改完一个验证一个。只有用户反馈“验证通过”且自动备份后，才能开启下一个接口改造。
7. **诊断优先与零幻觉原则**：严禁甩锅缓存。遇到 Bug 必须编写诊断脚本精确定位根源，严禁盲目猜想和反复试错。

## 3. 各模块完成情况与遗留问题 (Module Status)

### 3.1 Loyalty 模块 (忠诚度与积分)
- **已完成**：LoyaltyProxyUI 重构，MyAccount 集成，PunchCard 打卡逻辑修复，生日积分与月度奖励引擎 (MonthlyRewards) 完成，CRM 布局已统一。并且已经**彻底完成了**所有旧版 `wp_ajax_` 的清缴和向 REST API 的迁移。
- **遗留问题**：
  - MCD (Customer Rules) 与积分变动并发时的底层防御还需要在 REST API 全面接管后做最终的压测。

### 3.2 Promo 模块 (促销与打折规则)
- **已完成**：Phase 3 促销引擎改造，PromoEngineFix，整合进入了统一的设置与列表 UI，折扣条件触发已实现前后端分离计算。
  - **Coupons History (Reports)**：完成了原生无刷新分页（Seamless Pagination）、多维度展开式 Filter（状态、活动来源、有效期）以及排序功能的深度整合。
- **遗留问题**：
  - 需要确保后续 REST API 对于前端传来的购物车状态具备 100% 的防篡改校验。
  - **组件化迁移**：目前 Coupons History 使用的是 Vanilla JS 直接渲染，后期如果继续扩展，可能需要统一使用 Alpine JS 或 Vue 重构以完全对齐 Menu 模块。

### 3.3 Menu 模块 (菜单、标签与修饰符)
- **已完成**：MenuBuilder 完全独立化，响应式 Menu UI 重构，SVG 渲染支持，FoodLabels 社交分享，Modifiers 选择器优化 (VariationModifiers)。
- **遗留问题**：
  - 复杂 Item Modifiers（例如必选 / 互斥的多级修饰符）的交互验证目前仍依赖部分老旧 JS 逻辑，未来需彻底重写为 Alpine JS 响应式状态。

### 3.4 Reservation 模块 (当前阶段)
- **已完成**：ReservationFormDynamics 表单动态化，ReservationUI 与 AdminUI 现代化，整合 FluentCRM 风格列表，实现 List/Calendar 视图双轨，完成 Bulk Actions (批量操作) REST 接口对接。

## 4. Reservation 模块故障复盘与避免方案 (Post-Mortem & Avoidance Strategy)

在改造 Reservation 模块时暴露了严重的执行质量问题，以下是故障复盘与永久防范策略：

- **故障 1：UI 状态选项丢失 (Bulk Actions)**
  - *原因*：在剥离旧版表格向 Alpine JS 迁移时，只编写了下拉框外壳，遗忘了迁移旧版的 Bulk Actions（Confirm, Cancel 等）以及复选框的双向绑定。
  - *避免方案*：在重构任何 UI 组件前，必须使用 `grep_search` 强制列出旧版模板中所有的表单控件和状态枚举值。重构后必须逐一核对清单。

- **故障 2：PHP 致命语法错误 (Fatal Parse Error)**
  - *原因*：在使用 `multi_replace_file_content` 替换 `class-o100-reservation.php` 时，替换块漏掉了 `foreach` 循环的一个右大括号 `}`。
  - *避免方案*：执行替换操作前必须精准对齐代码边界；**所有 PHP 文件修改后，必须立即在后台隐式执行 `php -l <file>` 进行语法 lint 校验**，若有错误立即自行修复，绝不能让崩溃的页面展示给用户。

- **故障 3：前端表单样式突兀 (Hardcoded CSS Conflict)**
  - *原因*：在微件中硬编码了强主观倾向的 CSS（如 8px 圆角，特定的 border 和 padding），导致与主题 (Theme) 默认的 input/select 样式产生冲突和割裂。
  - *避免方案*：**禁止越权设计**。插件前端表单控件缺省必须 100% 继承当前主题样式，去除所有尺寸与边框的硬编码；必须优先使用 WordPress 和 WooCommerce 的原生/通用类名（如 `woocommerce-message`, `button alt`）以确保风格原生融合。

---
**准备就绪**：请在新的 Chat 窗口中告知读取此 `HANDOVER_MODULE_MIGRATION.md` 文件，以便恢复最高效率的执行状态。

## 5. Promo / UI 模块故障复盘与避免方案 (Post-Mortem & Avoidance Strategy - Session 2)

在本次 Promo 模块 (Coupons History) 列表改造中暴露了以下问题，现已列入 AGENTS.md 防御性编程规范：

- **故障 1：后台分页按钮触发整页刷新 (Form Submission Bug)**
  - *原因*：WordPress 后台存在隐式 `<form>` 包裹，手写 `<button>` 未加 `type="button"` 导致点击分页时误触发表单提交。
  - *避免方案*：任何后台非提交类的交互按钮，必须强制添加 `type="button"` 且在 JS `onclick` 中返回 `false` 拦截冒泡。

- **故障 2：无刷新数据加载时的视觉闪烁 (Flicker on Update)**
  - *原因*：AJAX 加载数据前暴力清空了 `tbody.innerHTML`。
  - *避免方案*：**禁止请求前摧毁 DOM**。加载状态必须使用 `opacity: 0.5` 等平滑淡出的骨架占位策略，待数据返回后瞬间替换内容，实现真正的 Seamless 体验。

- **故障 3：SQL JOIN 关联断裂 (Database Join Failure)**
  - *原因*：主观臆断 `o100_loyalty_transactions` 存在 `promo_id` 列，导致 500 错误白屏。
  - *避免方案*：严禁盲写 SQL JOIN。必须先通过代码工具检查数据库 Schema，无实体关联键时应使用 PHP 代码级合并或调整字段结构。

- **故障 4：盲目自创 UI 结构 (Standard Component Deviation)**
  - *原因*：未仔细参考 Menu Items 中的标准列表与过滤栏（Expandable Filter / Pagination）就自行手写了简易下拉框。
  - *避免方案*：凡涉及标准控件（列表、工具栏、分页），**强制优先提取复用 `core/menu-maker/views/` 等已定型的源码 HTML 结构**，保持全局交互和视觉样式的像素级统一。

## 6. REST API Phase 2 审计结果 (Phase 2 Audit Results)

经过 AST 扫描与源码级核查，对系统剩余模块 API 化进度的最终确认如下：
- **Reservation & Menu 模块**：已 100% 完成 REST API 化并接入 Alpine.js，前期交接文档状态存在滞后。这两个模块已达标闭环。
- **Tools (Settings) 模块**：严重技术债区。仍残留高达 13 个 `wp_ajax_o100_save_*` 和 `wp_ajax_o100_mcd_search_*` 接口，前端表单仍使用老旧 PHP 拼接。
- **Customer (CRM) 模块**：未改造。仍依赖传统 AJAX 与 Admin Post。
- **Notification 模块**：未改造。
- **Automation 模块**：未改造。后端代码极为庞大耦合（单文件达 119KB）。

**战略调整结论**：
全面启动 REST API Phase 2。按照由底层到高层的架构重构逻辑，进攻序列为：Tools (Settings) -> Customer (CRM) -> Notification -> Automation。首战目标直接锁定 **Tools (Settings) 模块**。
