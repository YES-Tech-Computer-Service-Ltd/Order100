<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $total_pages > 1 ) : ?>
	<div class="p-4 border-t border-slate-200 flex items-center justify-between">
		<div>
			<p class="text-sm text-slate-700">
				Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min( $offset + $per_page, $total_items ); ?></span> of <span class="font-medium"><?php echo $total_items; ?></span> results
			</p>
		</div>
		<div>
			<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
				<?php
				if ( $paged > 1 ) {
					echo '<a href="#" @click.prevent="goToPage(' . ( $paged - 1 ) . ')" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">Previous</a>';
				}

				for ( $i = 1; $i <= $total_pages; $i++ ) {
					$active_class = $paged === $i ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50';
					echo '<a href="#" @click.prevent="goToPage(' . $i . ')" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $active_class . '">' . $i . '</a>';
				}

				if ( $paged < $total_pages ) {
					echo '<a href="#" @click.prevent="goToPage(' . ( $paged + 1 ) . ')" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50">Next</a>';
				}
				?>
			</nav>
		</div>
	</div>
<?php else : ?>
	<div class="p-4 border-t border-slate-200 flex items-center justify-between">
		<div>
			<p class="text-sm text-slate-700">
				Showing <span class="font-medium"><?php echo $total_items > 0 ? 1 : 0; ?></span> to <span class="font-medium"><?php echo $total_items; ?></span> of <span class="font-medium"><?php echo $total_items; ?></span> results
			</p>
		</div>
	</div>
<?php endif; ?>
