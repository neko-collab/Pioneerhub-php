<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pioneer Hub - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/admin-style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4568dc;
            --secondary-color: #b06ab3;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #4568dc, #b06ab3);
            min-height: 100vh;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, .8);
            padding: .5rem 1rem;
            margin-bottom: 3px;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, .1);
        }
        
        .sidebar .nav-link .feather {
            margin-right: 4px;
        }
        
        main {
            padding-top: 56px;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        
        .navbar {
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075);
        }
        
        .navbar-toggler {
            border: none;
        }
        
        .content-wrapper {
            margin-left: 240px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
                padding: 0;
            }
            
            .content-wrapper {
                margin-left: 0;
            }
        }
        
        .border-left-primary {
            border-left: 4px solid var(--primary-color);
        }
        
        .border-left-success {
            border-left: 4px solid #1cc88a;
        }
        
        .border-left-warning {
            border-left: 4px solid #f6c23e;
        }
        
        .border-left-info {
            border-left: 4px solid #36b9cc;
        }
        
        .border-left-danger {
            border-left: 4px solid #e74a3b;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, #3758bc, #975aa0);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="text-center py-4 mb-3">
                    <h3 class="text-white">Pioneer Hub</h3>
                    <div class="small text-white-50">Admin Panel</div>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" href="dashboard.php">
                            <i class="fas fa-home fa-fw me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'courses.php') !== false ? 'active' : '' ?>" href="courses.php">
                            <i class="fas fa-book fa-fw me-2"></i>
                            Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'instructors.php') !== false ? 'active' : '' ?>" href="instructors.php">
                            <i class="fas fa-chalkboard-teacher fa-fw me-2"></i>
                            Instructors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'internships.php') !== false ? 'active' : '' ?>" href="internships.php">
                            <i class="fas fa-briefcase fa-fw me-2"></i>
                            Internships
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'jobs.php') !== false ? 'active' : '' ?>" href="jobs.php">
                            <i class="fas fa-briefcase fa-fw me-2"></i>
                            Jobs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'projects.php') !== false ? 'active' : '' ?>" href="projects.php">
                            <i class="fas fa-project-diagram fa-fw me-2"></i>
                            Projects
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users.php') !== false ? 'active' : '' ?>" href="users.php">
                            <i class="fas fa-users fa-fw me-2"></i>
                            Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : '' ?>" href="settings.php">
                            <i class="fas fa-cog fa-fw me-2"></i>
                            Settings
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt fa-fw me-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm ms-md-3 col-md-9 col-lg-10">
                    <div class="container-fluid">
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <span class="navbar-brand">Pioneer Hub Admin</span>
                        <div class="d-flex">
                            <div class="dropdown">
                                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['admin_name']) ?>&background=random" width="32" height="32" class="rounded-circle me-2">
                                    <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser">
                                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                    <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
