<?php
// Pagination helper functions

function renderPaginationInfo($offset, $limit, $totalRecords) {
    $start = $offset + 1;
    $end = min($offset + $limit, $totalRecords);
    echo '<div class="pagination-info">';
    echo "Showing {$start} to {$end} of {$totalRecords} entries";
    echo '</div>';
}

function renderPagination($currentPage, $totalPages) {
    if ($totalPages <= 1) return;
    ?>
    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a href="?page=1" class="pagination-btn">
                <i class="fas fa-angle-double-left"></i> First
            </a>
            <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">
                <i class="fas fa-angle-left"></i> Previous
            </a>
        <?php endif; ?>
        
        <?php
        // Calculate page range to display
        $start_page = max(1, $currentPage - 2);
        $end_page = min($totalPages, $currentPage + 2);
        
        if ($start_page > 1) {
            echo '<span class="page-dots">...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <a href="?page=<?php echo $i; ?>" 
               class="page-number <?php echo $i == $currentPage ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($end_page < $totalPages): ?>
            <span class="page-dots">...</span>
        <?php endif; ?>
        
        <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-btn">
                Next <i class="fas fa-angle-right"></i>
            </a>
            <a href="?page=<?php echo $totalPages; ?>" class="pagination-btn">
                Last <i class="fas fa-angle-double-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php
}
?>