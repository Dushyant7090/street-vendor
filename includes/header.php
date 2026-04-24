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
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Luxury Theme CSS -->
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="/street_vendor/assets/css/style.css">
    <?php if (!empty($adminPage)): ?>
    <link rel="stylesheet" href="/street_vendor/assets/css/admin.css">
    <?php endif; ?>
</head>
<body<?php echo !empty($adminPage) ? ' class="admin-theme"' : ''; ?>>
