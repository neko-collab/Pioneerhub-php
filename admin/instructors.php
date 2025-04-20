<?php
session_start();
include '../backend/db.php';
include '../backend/utilities.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle adding instructor details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_details') {
        $user_id = $_POST['user_id'] ?? '';
        $specialization = $_POST['specialization'] ?? '';
        $bio = $_POST['bio'] ?? '';
        $qualification = $_POST['qualification'] ?? '';
        $experience_years = $_POST['experience_years'] ?? 0;
        
        if (!empty($user_id)) {
            // Check if details already exist
            $check = executeQuery(
                "SELECT id FROM instructor_details WHERE user_id = ?", 
                [$user_id], 
                "i"
            );
            $check_result = $check->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing record
                $row = $check_result->fetch_assoc();
                $detail_id = $row['id'];
                
                executeQuery(
                    "UPDATE instructor_details SET specialization=?, bio=?, qualification=?, experience_years=? WHERE id=?",
                    [$specialization, $bio, $qualification, $experience_years, $detail_id],
                    "sssii"
                );
                
                header('Location: instructors.php?msg=updated');
                exit;
            } else {
                // Insert new record
                executeQuery(
                    "INSERT INTO instructor_details (user_id, specialization, bio, qualification, experience_years) VALUES (?, ?, ?, ?, ?)",
                    [$user_id, $specialization, $bio, $qualification, $experience_years],
                    "isssi"
                );
                
                header('Location: instructors.php?msg=added');
                exit;
            }
        }
    }
}

// Get all instructors with their details
$instructors = [];
$result = executeQuery(
    "SELECT u.id, u.name, u.email, u.profile_pic, u.created_at, 
            COALESCE(id.specialization, '') as specialization,
            COALESCE(id.bio, '') as bio,
            COALESCE(id.qualification, '') as qualification,
            COALESCE(id.experience_years, 0) as experience_years
     FROM users u
     LEFT JOIN instructor_details id ON u.id = id.user_id
     WHERE u.role='instructor'
     ORDER BY u.name ASC",
    [],
    ""
);

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
    <h1 class="h3 mb-2 text-gray-800">Instructor Management</h1>
    <p class="mb-4">Manage instructor profiles and specializations.</p>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Instructor details added successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Instructor details updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Instructors</h6>
            <a href="add_instructor.php" class="btn btn-primary">
                <i class="fas fa-plus fa-sm"></i> Add New Instructor
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Specialization</th>
                            <th>Qualification</th>
                            <th>Experience</th>
                            <th>Courses</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($instructors)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No instructors found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($instructors as $instructor): ?>
                                <tr>
                                    <td><?= $instructor['id'] ?></td>
                                    <td>
                                        <?php if ($instructor['profile_pic']): ?>
                                            <img src="../uploads/<?= htmlspecialchars($instructor['profile_pic']) ?>" class="rounded-circle me-2" width="30" height="30">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($instructor['name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($instructor['email']) ?></td>
                                    <td><?= htmlspecialchars($instructor['specialization']) ?: '<span class="text-muted">Not specified</span>' ?></td>
                                    <td><?= htmlspecialchars($instructor['qualification']) ?: '<span class="text-muted">Not specified</span>' ?></td>
                                    <td><?= $instructor['experience_years'] ? $instructor['experience_years'] . ' years' : '<span class="text-muted">Not specified</span>' ?></td>
                                    <td>
                                        <?php
                                        // Get course count for this instructor
                                        $count_result = executeQuery(
                                            "SELECT COUNT(*) as count FROM courses WHERE instructor_id = ?",
                                            [$instructor['id']],
                                            "i"
                                        );
                                        $count_row = $count_result->get_result()->fetch_assoc();
                                        $course_count = $count_row['count'] ?? 0;
                                        echo "<span class='badge bg-info'>$course_count courses</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-btn" 
                                            data-id="<?= $instructor['id'] ?>"
                                            data-name="<?= htmlspecialchars($instructor['name']) ?>"
                                            data-specialization="<?= htmlspecialchars($instructor['specialization']) ?>"
                                            data-bio="<?= htmlspecialchars($instructor['bio']) ?>"
                                            data-qualification="<?= htmlspecialchars($instructor['qualification']) ?>"
                                            data-experience="<?= $instructor['experience_years'] ?>"
                                            data-bs-toggle="modal" data-bs-target="#editInstructorModal">
                                            <i class="fas fa-edit"></i> Edit Details
                                        </button>
                                        <a href="instructor_courses.php?id=<?= $instructor['id'] ?>" class="btn btn-sm btn-primary mt-1">
                                            <i class="fas fa-book"></i> View Courses
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Instructor Modal -->
<div class="modal fade" id="editInstructorModal" tabindex="-1" aria-labelledby="editInstructorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editInstructorModalLabel">Edit Instructor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_details">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Instructor Name</label>
                        <input type="text" class="form-control" id="edit_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specialization" class="form-label">Specialization</label>
                        <input type="text" class="form-control" id="edit_specialization" name="specialization" placeholder="E.g., Web Development, Machine Learning, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="qualification" class="form-label">Qualification</label>
                        <input type="text" class="form-control" id="edit_qualification" name="qualification" placeholder="E.g., PhD in Computer Science, Masters in Data Science, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="experience_years" class="form-label">Years of Experience</label>
                        <input type="number" class="form-control" id="edit_experience" name="experience_years" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="edit_bio" name="bio" rows="4" placeholder="Professional background and experience..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Details</button>
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
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const specialization = this.getAttribute('data-specialization');
            const bio = this.getAttribute('data-bio');
            const qualification = this.getAttribute('data-qualification');
            const experience = this.getAttribute('data-experience');
            
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_specialization').value = specialization;
            document.getElementById('edit_bio').value = bio;
            document.getElementById('edit_qualification').value = qualification;
            document.getElementById('edit_experience').value = experience;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
