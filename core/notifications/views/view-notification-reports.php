<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'o100_notification_logs';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
	echo '<div style="padding: 24px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 24px;">';
	echo '<p style="color: #64748b; margin: 0;">' . esc_html__( 'Log table not initialized yet. Send a test message first.', 'order100' ) . '</p>';
	echo '</div>';
	return;
}

// Pagination
$paged     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page  = isset( $_GET['per_page'] ) ? max( 10, intval( $_GET['per_page'] ) ) : 20;
$offset    = ( $paged - 1 ) * $per_page;

// Sort
$sort_by   = isset( $_GET['sort'] ) ? sanitize_text_field( $_GET['sort'] ) : 'created_at';
$sort_dir  = isset( $_GET['dir'] ) && strtoupper( $_GET['dir'] ) === 'ASC' ? 'ASC' : 'DESC';
$allowed_sorts = array( 'created_at', 'type', 'target', 'status' );
if ( ! in_array( $sort_by, $allowed_sorts, true ) ) {
	$sort_by = 'created_at';
}

// Filters
$filter_type   = isset( $_GET['filter_type'] ) ? sanitize_text_field( $_GET['filter_type'] ) : '';
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
$search_query  = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

$where      = "1=1";
$where_args = array();

if ( ! empty( $filter_type ) ) {
	$where .= " AND type = %s";
	$where_args[] = $filter_type;
}
if ( ! empty( $filter_status ) ) {
	$where .= " AND status = %s";
	$where_args[] = $filter_status;
}
if ( ! empty( $search_query ) ) {
	$search_like   = '%' . $wpdb->esc_like( $search_query ) . '%';
	$where        .= " AND (target LIKE %s OR subject LIKE %s)";
	$where_args[]  = $search_like;
	$where_args[]  = $search_like;
}

$order_clause = "$sort_by $sort_dir";

if ( ! empty( $where_args ) ) {
	$total_items = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE {$where}", $where_args ) );
	$logs        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE {$where} ORDER BY $order_clause LIMIT %d OFFSET %d", array_merge( $where_args, array( $per_page, $offset ) ) ) );
} else {
	$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}" );
	$logs        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY $order_clause LIMIT %d OFFSET %d", $per_page, $offset ) );
}

$total_pages   = ceil( $total_items / $per_page );
$sort_labels   = array( 'created_at' => 'Date', 'type' => 'Type', 'target' => 'Target', 'status' => 'Status' );
$current_sort_label = isset( $sort_labels[ $sort_by ] ) ? $sort_labels[ $sort_by ] : 'Date';
$has_active_filter  = ! empty( $filter_type ) || ! empty( $filter_status );
?>

<style>
/* ═══ Notification Reports — Pure Scoped CSS (no Tailwind) ═══ */
.o100-rpt { padding: 2rem 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.o100-rpt *, .o100-rpt *::before, .o100-rpt *::after { box-sizing: border-box !important; }

/* Title */
.o100-rpt-title { display: flex; align-items: center; gap: 12px; margin: 0 0 16px; }
.o100-rpt-title h2 { margin: 0; font-size: 18px; font-weight: 600; color: #1e293b; flex: 1; }

/* Card */
.o100-rpt-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: visible; }

/* Toolbar */
.o100-rpt-toolbar { display: flex !important; align-items: center !important; gap: 12px !important; padding: 16px !important; border-bottom: 1px solid #e2e8f0 !important; }

/* Search */
.o100-rpt-search { position: relative !important; flex: 1 1 0% !important; max-width: 380px !important; min-width: 160px !important; }
.o100-rpt-search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8; pointer-events: none; }
.o100-rpt-search input[type="search"] {
	display: block !important; width: 100% !important; height: 36px !important;
	padding: 0 12px 0 36px !important; margin: 0 !important;
	border: 1px solid #cbd5e1 !important; border-radius: 6px !important;
	font-size: 14px !important; color: #334155 !important; background: #fff !important;
	box-shadow: none !important; outline: none !important; line-height: 36px !important;
	-webkit-appearance: none !important;
}
.o100-rpt-search input:focus { border-color: #F59322 !important; box-shadow: 0 0 0 2px rgba(99,102,241,0.15) !important; }

/* Toolbar Buttons */
.o100-rpt-btn {
	display: inline-flex !important; align-items: center !important; gap: 6px !important;
	height: 36px !important; padding: 0 14px !important;
	border: 1px solid #cbd5e1 !important; border-radius: 6px !important;
	background: #fff !important; color: #475569 !important;
	font-size: 14px !important; font-weight: 500 !important;
	cursor: pointer !important; white-space: nowrap !important;
	line-height: 1 !important; flex-shrink: 0 !important;
	transition: all 0.15s !important;
}
.o100-rpt-btn:hover { background: #f8fafc !important; border-color: #94a3b8 !important; }
.o100-rpt-btn.is-active { background: #fff7ed !important; border-color: #a5b4fc !important; color: #d97b06 !important; }
.o100-rpt-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
.o100-rpt-btn .chevron { width: 12px; height: 12px; margin-left: 2px; }

/* Filter Row */
.o100-rpt-filters { display: none; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
.o100-rpt-filters.is-open { display: flex !important; flex-wrap: wrap !important; gap: 12px !important; align-items: center !important; }
.o100-rpt-filters select {
	display: inline-block !important; width: auto !important; min-width: 140px !important;
	height: 34px !important; padding: 0 28px 0 12px !important;
	border: 1px solid #cbd5e1 !important; border-radius: 6px !important;
	font-size: 13px !important; color: #334155 !important; background: #fff !important;
	cursor: pointer !important; box-shadow: none !important; outline: none !important;
}

/* Sort Dropdown */
.o100-rpt-sort-wrap { position: relative; }
.o100-rpt-sort-panel {
	display: none; position: absolute; right: 0; top: 100%; margin-top: 4px;
	width: 210px; background: #fff; border-radius: 8px;
	box-shadow: 0 10px 25px rgba(0,0,0,0.15); border: 1px solid #e2e8f0;
	padding: 8px 0; z-index: 9999;
}
.o100-rpt-sort-panel.is-open { display: block; }
.o100-rpt-sort-head { padding: 4px 16px 6px; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
.o100-rpt-sort-opt {
	display: flex !important; align-items: center !important; justify-content: space-between !important;
	width: 100% !important; padding: 8px 16px !important; border: none !important;
	background: none !important; font-size: 14px !important; color: #475569 !important;
	cursor: pointer !important; text-align: left !important;
}
.o100-rpt-sort-opt:hover { background: #f8fafc !important; }
.o100-rpt-sort-opt.is-active { color: #d97b06 !important; background: #fff7ed !important; font-weight: 600 !important; }
.o100-rpt-sort-opt .check { display: none; }
.o100-rpt-sort-opt.is-active .check { display: inline; color: #F59322; font-weight: bold; }
.o100-rpt-sort-sep { height: 1px; background: #f1f5f9; margin: 6px 0; }
.o100-rpt-sort-dirs { display: flex; gap: 4px; padding: 4px 12px; }
.o100-rpt-sort-dir {
	flex: 1; display: inline-flex !important; align-items: center !important; justify-content: center !important;
	gap: 4px; padding: 6px 10px !important; border: 1px solid #e2e8f0 !important;
	border-radius: 6px !important; font-size: 12px !important; font-weight: 600 !important;
	color: #64748b !important; background: #fff !important; cursor: pointer !important;
}
.o100-rpt-sort-dir:hover { background: #f8fafc !important; }
.o100-rpt-sort-dir.is-active { background: #fff7ed !important; border-color: #818cf8 !important; color: #d97b06 !important; }
.o100-rpt-sort-dir svg { width: 12px; height: 12px; }

/* Table */
.o100-rpt-table { width: 100%; border-collapse: collapse; margin: 0; }
.o100-rpt-table thead tr { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.o100-rpt-table th { padding: 12px 24px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; white-space: nowrap; }
.o100-rpt-table td { padding: 14px 24px; font-size: 14px; color: #475569; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.o100-rpt-table tbody tr:hover { background: #f8fafc; }
.o100-rpt-table tbody tr:last-child td { border-bottom: none; }

/* Badge */
.o100-rpt-badge { display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: 12px; font-weight: 500; line-height: 1.6; }
.o100-rpt-badge--sent { background: #d1fae5; color: #065f46; }
.o100-rpt-badge--failed { background: #fee2e2; color: #991b1b; }
.o100-rpt-badge--pending { background: #fef3c7; color: #92400e; }

/* View link */
.o100-rpt-view { background: none !important; border: none !important; padding: 0 !important; color: #F59322 !important; font-size: 14px !important; font-weight: 500 !important; cursor: pointer !important; }
.o100-rpt-view:hover { color: #3730a3 !important; }

/* Pagination */
.o100-rpt-pag { display: flex !important; align-items: center !important; justify-content: space-between !important; padding: 12px 24px !important; border-top: 1px solid #e2e8f0 !important; }
.o100-rpt-pag-info { font-size: 13px; color: #475569; }
.o100-rpt-pag-info b { font-weight: 600; }
.o100-rpt-pag-right { display: flex !important; align-items: center !important; gap: 12px !important; }
.o100-rpt-pag select {
	display: inline-block !important; width: auto !important; height: 30px !important;
	padding: 0 24px 0 8px !important; border: 1px solid #e2e8f0 !important;
	border-radius: 4px !important; font-size: 13px !important; color: #475569 !important;
	background: #fff !important; cursor: pointer !important;
}
.o100-rpt-pg-btn {
	display: inline-flex !important; align-items: center !important; justify-content: center !important;
	min-width: 32px !important; height: 32px !important; padding: 0 8px !important;
	border: 1px solid #e2e8f0 !important; font-size: 13px !important;
	color: #475569 !important; background: #fff !important;
	cursor: pointer !important; text-decoration: none !important;
}
.o100-rpt-pg-btn:first-child { border-radius: 6px 0 0 6px !important; }
.o100-rpt-pg-btn:last-child { border-radius: 0 6px 6px 0 !important; }
.o100-rpt-pg-btn + .o100-rpt-pg-btn { margin-left: -1px !important; }
.o100-rpt-pg-btn:hover { background: #f8fafc !important; z-index: 1 !important; color: #475569 !important; text-decoration: none !important; }
.o100-rpt-pg-btn.is-active { background: #fff7ed !important; border-color: #F59322 !important; color: #F59322 !important; font-weight: 600 !important; z-index: 2 !important; }

/* Modal */
.o100-rpt-modal-bg { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:100000; justify-content:center; align-items:flex-start; padding-top:10vh; }
.o100-rpt-modal-bg.is-open { display:flex; }
.o100-rpt-modal-box { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:720px; max-width:90vw; max-height:80vh; overflow:hidden; border:1px solid #e2e8f0; }
.o100-rpt-modal-head { display:flex; justify-content:space-between; align-items:center; padding:16px 24px; border-bottom:1px solid #e2e8f0; }
.o100-rpt-modal-head h3 { margin:0; font-size:16px; font-weight:600; color:#0f172a; }
.o100-rpt-modal-close { background:none !important; border:none !important; cursor:pointer; color:#94a3b8; font-size:20px; line-height:1; padding:4px; }
.o100-rpt-modal-close:hover { color:#475569; }
.o100-rpt-modal-body { padding:24px; overflow-y:auto; max-height:calc(80vh - 130px); }
.o100-rpt-modal-code { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:16px; font-family:'SF Mono',Consolas,monospace; font-size:13px; color:#475569; white-space:pre-wrap; word-break:break-all; line-height:1.6; }
.o100-rpt-modal-foot { padding:12px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; text-align:right; }
.o100-rpt-modal-foot button { padding:8px 16px !important; border:1px solid #e2e8f0 !important; border-radius:6px !important; background:#fff !important; color:#475569 !important; font-size:13px !important; font-weight:500 !important; cursor:pointer !important; }
.o100-rpt-modal-foot button:hover { background:#f1f5f9 !important; }
</style>

<div class="o100-rpt">

	<!-- Title -->
	<div class="o100-rpt-title">
		<h2>Sending Reports</h2>
	</div>

	<!-- Card -->
	<div class="o100-rpt-card">

		<!-- Toolbar -->
		<div class="o100-rpt-toolbar">
			<div class="o100-rpt-search">
				<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
				<input type="search" id="o100-rpt-search" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search by name, email...">
			</div>

			<button type="button" class="o100-rpt-btn <?php echo $has_active_filter ? 'is-active' : ''; ?>" id="o100-rpt-filter-toggle" onclick="var r=document.getElementById('o100-rpt-filters-row');r.classList.toggle('is-open');this.classList.toggle('is-active');">
				<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
				Filter
			</button>

			<div class="o100-rpt-sort-wrap">
				<button type="button" class="o100-rpt-btn" onclick="document.getElementById('o100-rpt-sort-panel').classList.toggle('is-open');">
					<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
					<span id="o100-rpt-sort-label">Sort by <?php echo esc_html( $current_sort_label ); ?></span>
					<svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
				</button>
				<div id="o100-rpt-sort-panel" class="o100-rpt-sort-panel">
					<div class="o100-rpt-sort-head">Sort By</div>
					<?php foreach ( $sort_labels as $field => $label ) : ?>
					<button type="button" class="o100-rpt-sort-opt <?php echo $sort_by === $field ? 'is-active' : ''; ?>" onclick="o100RptSetSort('<?php echo esc_attr($field); ?>','<?php echo esc_attr($label); ?>')">
						<span><?php echo esc_html($label); ?></span><span class="check">✓</span>
					</button>
					<?php endforeach; ?>
					<div class="o100-rpt-sort-sep"></div>
					<div class="o100-rpt-sort-dirs">
						<button type="button" class="o100-rpt-sort-dir <?php echo $sort_dir === 'ASC' ? 'is-active' : ''; ?>" onclick="o100RptSetDir('ASC')">Asc <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg></button>
						<button type="button" class="o100-rpt-sort-dir <?php echo $sort_dir === 'DESC' ? 'is-active' : ''; ?>" onclick="o100RptSetDir('DESC')">Desc <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg></button>
					</div>
				</div>
			</div>
		</div>

		<!-- Filter Row -->
		<div id="o100-rpt-filters-row" class="o100-rpt-filters <?php echo $has_active_filter ? 'is-open' : ''; ?>">
			<select id="o100-rpt-filter-type" onchange="o100RptApplyFilters()">
				<option value="">All Types</option>
				<option value="email" <?php selected($filter_type,'email'); ?>>Email</option>
				<option value="sms" <?php selected($filter_type,'sms'); ?>>SMS</option>
				<option value="voice" <?php selected($filter_type,'voice'); ?>>Voice Call</option>
			</select>
			<select id="o100-rpt-filter-status" onchange="o100RptApplyFilters()">
				<option value="">All Statuses</option>
				<option value="sent" <?php selected($filter_status,'sent'); ?>>Sent</option>
				<option value="failed" <?php selected($filter_status,'failed'); ?>>Failed</option>
				<option value="pending" <?php selected($filter_status,'pending'); ?>>Pending</option>
			</select>
		</div>

		<!-- Table -->
		<div style="overflow-x:auto;">
			<table class="o100-rpt-table">
				<thead><tr>
					<th style="width:170px;">Date</th>
					<th style="width:80px;">Type</th>
					<th style="width:250px;">Target</th>
					<th>Subject / Context</th>
					<th style="width:90px;">Status</th>
					<th style="width:80px;text-align:center;">Actions</th>
				</tr></thead>
				<tbody>
				<?php if ( empty($logs) ) : ?>
					<tr><td colspan="6" style="text-align:center;padding:48px 24px;color:#94a3b8;">No records found.</td></tr>
				<?php else : foreach ($logs as $log) :
					$bm = $log->status === 'sent' ? 'sent' : ($log->status === 'failed' ? 'failed' : 'pending');
					$re = esc_attr($log->response ?: 'No gateway response recorded.');
				?>
					<tr>
						<td style="white-space:nowrap;"><?php echo esc_html($log->created_at); ?></td>
						<td style="white-space:nowrap;font-weight:500;"><?php echo esc_html(strtoupper($log->type)); ?></td>
						<td style="word-break:break-all;"><?php echo esc_html($log->target); ?></td>
						<td><?php echo esc_html($log->subject); ?></td>
						<td><span class="o100-rpt-badge o100-rpt-badge--<?php echo $bm; ?>"><?php echo esc_html(ucfirst($log->status)); ?></span></td>
						<td style="text-align:center;"><button type="button" class="o100-rpt-view" onclick="o100RptModal('<?php echo esc_js('Log #'.$log->id.' — '.strtoupper($log->type)); ?>',this.getAttribute('data-c'))" data-c="<?php echo $re; ?>">View</button></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ($total_pages > 1 || $total_items > 20) : ?>
		<div class="o100-rpt-pag">
			<div class="o100-rpt-pag-info">Showing <b><?php echo min($offset+1,$total_items); ?></b> to <b><?php echo min($offset+$per_page,$total_items); ?></b> of <b><?php echo intval($total_items); ?></b> results</div>
			<div class="o100-rpt-pag-right">
				<div style="display:flex;align-items:center;gap:6px;">
					<span style="font-size:13px;color:#64748b;">Per page:</span>
					<select id="o100-rpt-per-page" onchange="o100RptApplyFilters()">
						<option value="20" <?php selected($per_page,20); ?>>20</option>
						<option value="50" <?php selected($per_page,50); ?>>50</option>
						<option value="100" <?php selected($per_page,100); ?>>100</option>
					</select>
				</div>
				<?php if ($total_pages > 1) : ?>
				<div style="display:inline-flex;">
					<?php if ($paged > 1) : ?><a href="#" onclick="o100RptPage(<?php echo $paged-1; ?>);return false;" class="o100-rpt-pg-btn">&laquo;</a><?php endif; ?>
					<?php for ($i=max(1,$paged-2); $i<=min($total_pages,$paged+2); $i++) : ?>
					<a href="#" onclick="o100RptPage(<?php echo $i; ?>);return false;" class="o100-rpt-pg-btn <?php echo $i===$paged?'is-active':''; ?>"><?php echo $i; ?></a>
					<?php endfor; ?>
					<?php if ($paged < $total_pages) : ?><a href="#" onclick="o100RptPage(<?php echo $paged+1; ?>);return false;" class="o100-rpt-pg-btn">&raquo;</a><?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

	</div>

	<!-- Modal -->
	<div id="o100-rpt-modal" class="o100-rpt-modal-bg" onclick="if(event.target===this)o100RptModalClose()">
		<div class="o100-rpt-modal-box">
			<div class="o100-rpt-modal-head"><h3 id="o100-rpt-modal-t"></h3><button type="button" class="o100-rpt-modal-close" onclick="o100RptModalClose()">&times;</button></div>
			<div class="o100-rpt-modal-body"><h4 style="margin:0 0 8px;font-size:13px;font-weight:600;color:#475569;">Gateway Response:</h4><div id="o100-rpt-modal-c" class="o100-rpt-modal-code"></div></div>
			<div class="o100-rpt-modal-foot"><button type="button" onclick="o100RptModalClose()">Close</button></div>
		</div>
	</div>
</div>

<script>
var _rs='<?php echo esc_js($sort_by); ?>',_rd='<?php echo esc_js($sort_dir); ?>';
function o100RptUrl(p){
	var s=(document.getElementById('o100-rpt-search')||{}).value||'',
		ft=(document.getElementById('o100-rpt-filter-type')||{}).value||'',
		fs=(document.getElementById('o100-rpt-filter-status')||{}).value||'',
		pp=(document.getElementById('o100-rpt-per-page')||{}).value||'20',
		u='admin.php?page=o100-notifications';
	if(s)u+='&s='+encodeURIComponent(s);
	if(ft)u+='&filter_type='+encodeURIComponent(ft);
	if(fs)u+='&filter_status='+encodeURIComponent(fs);
	if(_rs&&_rs!=='created_at')u+='&sort='+_rs;
	if(_rd&&_rd!=='DESC')u+='&dir='+_rd;
	if(pp&&pp!=='20')u+='&per_page='+pp;
	if(p&&p>1)u+='&paged='+p;
	return u;
}
function o100RptApplyFilters(){try{localStorage.setItem('o100_notify_subtab','reports')}catch(e){}window.location.href=o100RptUrl(1);}
function o100RptPage(p){try{localStorage.setItem('o100_notify_subtab','reports')}catch(e){}window.location.href=o100RptUrl(p);}
function o100RptSetSort(f,l){_rs=f;o100RptApplyFilters();}
function o100RptSetDir(d){_rd=d;o100RptApplyFilters();}
function o100RptModal(t,c){document.getElementById('o100-rpt-modal-t').textContent=t;document.getElementById('o100-rpt-modal-c').textContent=c;document.getElementById('o100-rpt-modal').classList.add('is-open');}
function o100RptModalClose(){document.getElementById('o100-rpt-modal').classList.remove('is-open');}
document.addEventListener('keydown',function(e){if(e.key==='Escape')o100RptModalClose();});
var _si=document.getElementById('o100-rpt-search');if(_si)_si.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();o100RptApplyFilters();}});
document.addEventListener('click',function(e){var p=document.getElementById('o100-rpt-sort-panel');if(p&&!e.target.closest('.o100-rpt-sort-wrap'))p.classList.remove('is-open');});
</script>
