<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>Street Vendor Management</title>
    <meta name="description" content="Street Vendor License and Location Management System">
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Boxicons (Icon Library) -->
    <link href="/street_vendor/assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <?php if (!empty($adminPage)): ?>
    <!-- Bootstrap 5 CSS for Admin Redesign -->
    <link href="/street_vendor/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Admin Redesign CSS -->
    <link rel="stylesheet" href="/street_vendor/assets/css/admin-redesign.css">
    
    <!-- Theme Script -->
    <script>
        const savedTheme = localStorage.getItem('adminTheme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
    <?php elseif (!empty($vendorPage)): ?>
    <!-- Bootstrap 5 CSS for Vendor Redesign -->
    <link href="/street_vendor/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Vendor Redesign CSS -->
    <link rel="stylesheet" href="/street_vendor/assets/css/vendor-redesign.css">
    
    <script>
        const savedTheme = localStorage.getItem('vendorTheme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
    <?php else: ?>
    <!-- Main Stylesheet for Frontend -->
    <link rel="stylesheet" href="/street_vendor/assets/css/style.css">
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <?php endif; ?>
</head>
<body<?php
    if (!empty($adminPage)) echo ' class="admin-theme bg-background text-foreground"';
    elseif (!empty($vendorPage)) echo ' class="vendor-theme bg-background text-foreground"';
?>>
