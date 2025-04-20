<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if instructor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: instructors.php');
    exit;
}

$instructor_id = $_GET['id'];

// Get instructor details
$instructor = null;
$result = executeQuery(
    "SELECT u.*, id.specialization, id.bio, id.qualification, id.experience_years 
     FROM users u 
     LEFT JOIN instructor_details id ON u.id = id.user_id 
     WHERE u.id = ? AND u.role = 'instructor'", 
    [$instructor_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    if ($row = $result_set->fetch_assoc()) {
        $instructor = $row;
    } else {
        // Instructor not found
        header('Location: instructors.php');
        exit;
    }
    $result_set->close();
}

// Get instructor's courses
$courses = [];
$result = executeQuery(
    "SELECT c.*, COUNT(cr.id) as student_count 
     FROM courses c 
     LEFT JOIN course_registrations cr ON c.id = cr.course_id 
     WHERE c.instructor_id = ? 
     GROUP BY c.id
     ORDER BY c.created_at DESC", 
    [$instructor_id], 
    "i"
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $courses[] = $row;
    }
    $result_set->close();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Instructor Profile</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="instructors.php">Instructors</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($instructor['name']) ?></li>
                </ol>
            </nav>
        </div>
        <a href="instructors.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Instructors
        </a>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($instructor['profile_pic'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($instructor['profile_pic']) ?>" class="rounded-circle mb-3" width="150" height="150">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($instructor['name']) ?>&background=random" class="rounded-circle mb-3" width="150" height="150">
                    <?php endif; ?>
                    
                    <h4 class="mb-0"><?= htmlspecialchars($instructor['name']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($instructor['email']) ?></p>
                    
                    <?php if (!empty($instructor['specialization'])): ?>
                        <span class="badge bg-primary"><?= htmlspecialchars($instructor['specialization']) ?></span>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <p class="mb-0"><strong>Joined:</strong> <?= date('F d, Y', strtotime($instructor['created_at'])) ?></p>
                        <?php if (!empty($instructor['experience_years'])): ?>
                            <p class="mb-0"><strong>Experience:</strong> <?= $instructor['experience_years'] ?> years</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Professional Details</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($instructor['qualification'])): ?>
                        <div class="mb-3">
                            <h6 class="font-weight-bold">Qualification</h6>
                            <p><?= htmlspecialchars($instructor['qualification']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($instructor['bio'])): ?>
                        <div class="mb-3">
                            <h6 class="font-weight-bold">Biography</h6>
                            <p><?= nl2br(htmlspecialchars($instructor['bio'])) ?></p>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No biography provided.</p>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="edit_instructor.php?id=<?= $instructor_id ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Courses (<?= count($courses) ?>)</h6>
                    <a href="courses.php?add=1&instructor_id=<?= $instructor_id ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Assign New Course
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($courses)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No courses assigned to this instructor yet.</p>
                            <p class="text-muted">Use the button above to assign a course.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Price</th>
                                        <th>Trending</th>
                                        <th>Students</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($course['title']) ?></td>
                                            <td>Rs. <?= number_format($course['price'], 2) ?></td>
                                            <td>
                                                <?= $course['is_trending'] ? 
                                                    '<span class="badge bg-success">Yes</span>' : 
                                                    '<span class="badge bg-secondary">No</span>' ?>
                                            </td>
                                            <td><?= $course['student_count'] ?></td>
                                            <td><?= date('M d, Y', strtotime($course['created_at'])) ?></td>
                                            <td>
                                                <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Course Analytics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Courses</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($courses) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-book fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Students</div>
                                            <?php
                                            $total_students = 0;
                                            foreach ($courses as $course) {
                                                $total_students += $course['student_count'];
                                            }
                                            ?>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_students ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Trending Courses</div>
                                            <?php
                                            $trending_count = 0;
                                            foreach ($courses as $course) {
                                                if ($course['is_trending'] == 1) {
                                                    $trending_count++;
                                                }
                                            }
                                            ?>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $trending_count ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
