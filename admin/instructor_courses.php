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
    "SELECT u.*, COALESCE(id.specialization, '') as specialization
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

// Get courses by this instructor
$courses = [];
$result = executeQuery(
    "SELECT * FROM courses WHERE instructor_id = ? ORDER BY created_at DESC", 
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
            <h1 class="h3 mb-0 text-gray-800">Instructor Courses</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="instructors.php">Instructors</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($instructor['name']) ?>'s Courses</li>
                </ol>
            </nav>
        </div>
        <a href="instructors.php" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to Instructors
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Instructor Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if ($instructor['profile_pic']): ?>
                            <img src="/Pioneer/uploads/<?= htmlspecialchars($instructor['profile_pic']) ?>" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($instructor['name']) ?>&background=random" class="rounded-circle" width="150" height="150">
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="h4 mb-3 text-center"><?= htmlspecialchars($instructor['name']) ?></h2>
                    
                    <div class="mb-3">
                        <p class="text-muted mb-1">Email:</p>
                        <p><strong><?= htmlspecialchars($instructor['email']) ?></strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <p class="text-muted mb-1">Specialization:</p>
                        <p><?= !empty($instructor['specialization']) ? htmlspecialchars($instructor['specialization']) : '<span class="text-muted">Not specified</span>' ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <p class="text-muted mb-1">Member Since:</p>
                        <p><?= date('F d, Y', strtotime($instructor['created_at'])) ?></p>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="courses.php?add=1&instructor_id=<?= $instructor['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Assign New Course
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Courses by <?= htmlspecialchars($instructor['name']) ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($courses)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No courses found for this instructor.</p>
                            <p class="text-muted">Assign a course using the button on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Price</th>
                                        <th>Trending</th>
                                        <th>Created</th>
                                        <th>Students</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td><?= $course['id'] ?></td>
                                            <td><?= htmlspecialchars($course['title']) ?></td>
                                            <td>Rs. <?= number_format($course['price'], 2) ?></td>
                                            <td>
                                                <?= $course['is_trending'] ? 
                                                    '<span class="badge bg-success">Yes</span>' : 
                                                    '<span class="badge bg-secondary">No</span>' ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($course['created_at'])) ?></td>
                                            <td>
                                                <?php
                                                $count_result = executeQuery(
                                                    "SELECT COUNT(*) as count FROM course_registrations WHERE course_id = ?",
                                                    [$course['id']],
                                                    "i"
                                                );
                                                $count_row = $count_result->get_result()->fetch_assoc();
                                                $student_count = $count_row['count'] ?? 0;
                                                echo "<span class='badge bg-info'>$student_count students</span>";
                                                ?>
                                            </td>
                                            <td>
                                                <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="courses.php?edit=1&id=<?= $course['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-edit"></i> Edit
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
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
