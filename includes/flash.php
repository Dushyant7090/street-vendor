<?php
/**
 * Flash Message Display Component
 * Shows session-based flash messages and then clears them.
 */
if (isset($_SESSION['flash'])): 
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
<div class="flash-message flash-<?php echo $flash['type']; ?>">
    <span><?php echo $flash['message']; ?></span>
    <button class="close-flash">&times;</button>
</div>
<?php endif; ?>
