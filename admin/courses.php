<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle course deletion
if (isset($_GET['delete_id']) && !empty($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Delete the course
    $result = executeQuery("DELETE FROM courses WHERE id=?", [$delete_id], "i");
    
    // Redirect back to courses page
    header('Location: courses.php?msg=deleted');
    exit;
}

// Fetch all courses with instructor name
$courses = [];
$result = executeQuery(
    "SELECT courses.*, users.name as instructor_name 
     FROM courses 
     LEFT JOIN users ON courses.instructor_id = users.id 
     ORDER BY courses.created_at DESC", 
    [], 
    ""
);

if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $courses[] = $row;
    }
    $result_set->close();
}

// Fetch all instructors for the add/edit forms
$instructors = [];
$result = executeQuery("SELECT id, name, email FROM users WHERE role='instructor'", [], "");
if ($result) {
    $result_set = $result->get_result();
    while ($row = $result_set->fetch_assoc()) {
        $instructors[] = $row;
    }
    $result_set->close();
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-2 text-gray-800">Courses Management</h1>
    <p class="mb-4">Manage all courses on the Pioneer Hub platform.</p>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Course deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Courses</h6>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                <i class="fas fa-plus fa-sm"></i> Add New Course
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Instructor</th>
                            <th>Price</th>
                            <th>Trending</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?= $course['id'] ?></td>
                            <td><?= htmlspecialchars($course['title']) ?></td>
                            <td><?= htmlspecialchars($course['instructor_name'] ?? 'None') ?></td>
                            <td>Rs. <?= number_format($course['price'], 2) ?></td>
                            <td>
                                <?= $course['is_trending'] ? 
                                '<span class="badge bg-success">Yes</span>' : 
                                '<span class="badge bg-secondary">No</span>' ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($course['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info edit-btn" 
                                    data-id="<?= $course['id'] ?>"
                                    data-title="<?= htmlspecialchars($course['title']) ?>"
                                    data-description="<?= htmlspecialchars($course['description']) ?>"
                                    data-price="<?= $course['price'] ?>"
                                    data-instructor="<?= $course['instructor_id'] ?>"
                                    data-trending="<?= $course['is_trending'] ?>"
                                    data-bs-toggle="modal" data-bs-target="#editCourseModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="courses.php?delete_id=<?= $course['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this course?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCourseModalLabel">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="course_process.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price" class="form-label">Price (Rs. )</label>
                        <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="instructor_id" class="form-label">Instructor</label>
                        <select class="form-control" id="instructor_id" name="instructor_id" required>
                            <option value="">Select Instructor</option>
                            <?php foreach ($instructors as $instructor): ?>
                            <option value="<?= $instructor['id'] ?>"><?= htmlspecialchars($instructor['name']) ?> (<?= htmlspecialchars($instructor['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_trending" name="is_trending" value="1">
                        <label class="form-check-label" for="is_trending">Mark as Trending</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCourseModalLabel">Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="course_process.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Price ($)</label>
                        <input type="number" class="form-control" id="edit_price" name="price" min="0" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_instructor_id" class="form-label">Instructor</label>
                        <select class="form-control" id="edit_instructor_id" name="instructor_id" required>
                            <option value="">Select Instructor</option>
                            <?php foreach ($instructors as $instructor): ?>
                            <option value="<?= $instructor['id'] ?>"><?= htmlspecialchars($instructor['name']) ?> (<?= htmlspecialchars($instructor['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_trending" name="is_trending" value="1">
                        <label class="form-check-label" for="edit_is_trending">Mark as Trending</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit button clicks
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const courseId = this.getAttribute('data-id');
            const courseTitle = this.getAttribute('data-title');
            const courseDescription = this.getAttribute('data-description');
            const coursePrice = this.getAttribute('data-price');
            const courseInstructor = this.getAttribute('data-instructor');
            const courseTrending = this.getAttribute('data-trending') === '1';
            
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('edit_title').value = courseTitle;
            document.getElementById('edit_description').value = courseDescription;
            document.getElementById('edit_price').value = coursePrice;
            document.getElementById('edit_instructor_id').value = courseInstructor;
            document.getElementById('edit_is_trending').checked = courseTrending;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
