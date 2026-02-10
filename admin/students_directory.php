<?php
/**
 * Students Directory - AttendEase v3.0
 * View students organized by section with filtering
 * 
 * @package AttendEase
 * @version 3.0
 */

require_once 'config.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Allow admin, teacher, and staff to view
requireRole([ROLE_ADMIN, ROLE_TEACHER, ROLE_STAFF]);

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Students Directory';
$pageIcon = 'users';

$additionalCSS = ['../css/manual-attendance-modern.css?v=' . time()];

// Get filter parameters
$filterGradeLevel = $_GET['grade_level'] ?? '';
$filterSection = $_GET['section'] ?? '';
$filterSex = $_GET['sex'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Fetch grade levels for filter
try {
    $gradeLevelsStmt = $pdo->query("SELECT DISTINCT grade_level FROM sections 
        WHERE grade_level NOT IN ('K', 'Kindergarten', '1', '2', '3', '4', '5', '6', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6')
        AND grade_level NOT LIKE 'Kinder%'
        ORDER BY 
        CASE 
            WHEN grade_level LIKE 'Grade%' THEN CAST(SUBSTRING(grade_level, 7) AS UNSIGNED)
            ELSE 999 
        END");
    $gradeLevels = $gradeLevelsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $gradeLevels = [];
}

// Fetch sections based on grade level filter
try {
    if ($filterGradeLevel) {
        $sectionsStmt = $pdo->prepare("SELECT id, section_name, grade_level FROM sections WHERE grade_level = ? ORDER BY section_name");
        $sectionsStmt->execute([$filterGradeLevel]);
    } else {
        $sectionsStmt = $pdo->query("SELECT id, section_name, grade_level FROM sections ORDER BY grade_level, section_name");
    }
    $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sections = [];
}

// Detect available schema variations (students / attendance columns)
try {
    $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'");
    $colStmt->execute();
    $studentCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $studentCols = [];
}

try {
    $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance'");
    $colStmt->execute();
    $attendanceCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $attendanceCols = [];
}

$hasLrn = in_array('lrn', $studentCols, true);
$hasStudentId = in_array('student_id', $studentCols, true) || in_array('id', $studentCols, true);
$attendanceUsesLrn = in_array('lrn', $attendanceCols, true);
$attendanceUsesStudentId = in_array('student_id', $attendanceCols, true);
$hasSectionId = in_array('section_id', $studentCols, true);
$hasSectionName = in_array('section', $studentCols, true);

// Build query for students (schema-aware)
$params = [];

// Decide which identifier to use when querying attendance
$attendanceIdField = $attendanceUsesLrn ? 'lrn' : ($attendanceUsesStudentId ? 'student_id' : null);
$studentIdRef = $hasLrn ? 's.lrn' : ($hasStudentId ? 's.student_id' : 's.id');

// Subqueries for attendance counts and last attendance
if ($attendanceIdField !== null) {
    $monthlySub = "(SELECT COUNT(*) FROM attendance a WHERE a.{$attendanceIdField} = {$studentIdRef} AND MONTH(a.date) = MONTH(CURRENT_DATE) AND YEAR(a.date) = YEAR(CURRENT_DATE)) as monthly_attendance";
    $lastSub = "(SELECT MAX(date) FROM attendance a WHERE a.{$attendanceIdField} = {$studentIdRef}) as last_attendance";
} else {
    // Attendance table doesn't have expected id columns; fallback to zero/null
    $monthlySub = "0 as monthly_attendance";
    $lastSub = "NULL as last_attendance";
}

// Build JOIN to sections depending on whether students store section_id or section name
if ($hasSectionId) {
    $join = "LEFT JOIN sections sec ON s.section_id = sec.id";
} elseif ($hasSectionName) {
    $join = "LEFT JOIN sections sec ON s.section = sec.section_name";
} else {
    $join = "LEFT JOIN sections sec ON 1=0"; // no section info available
}

$query = "SELECT s.*, sec.section_name, sec.grade_level, {$monthlySub}, {$lastSub} FROM students s {$join} WHERE 1=1";

// Grade level filter uses sections table
if ($filterGradeLevel) {
    $query .= " AND sec.grade_level = ?";
    $params[] = $filterGradeLevel;
}

// Section filter: if students table has section_id, use it; otherwise resolve id->name
if ($filterSection) {
    if ($hasSectionId) {
        $query .= " AND s.section_id = ?";
        $params[] = $filterSection;
    } elseif ($hasSectionName) {
        // resolve section id to name
        try {
            $nameStmt = $pdo->prepare("SELECT section_name FROM sections WHERE id = ? LIMIT 1");
            $nameStmt->execute([$filterSection]);
            $secName = $nameStmt->fetchColumn();
            if ($secName) {
                $query .= " AND s.section = ?";
                $params[] = $secName;
            } else {
                // no matching section id -> no results
                $query .= " AND 1=0";
            }
        } catch (Exception $e) {
            $query .= " AND 1=0";
        }
    } else {
        $query .= " AND 1=0";
    }
}

// Sex/gender filter
if ($filterSex) {
    $query .= " AND (s.sex = ? OR s.gender = ?)";
    $params[] = $filterSex;
    $params[] = $filterSex;
}

// Search term: match against name and available identifier columns
if ($searchTerm) {
    $searchWildcard = "%{$searchTerm}%";
    $searchParts = ["s.first_name LIKE ?", "s.last_name LIKE ?"];
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    if ($hasLrn) {
        $searchParts[] = "s.lrn LIKE ?";
        $params[] = $searchWildcard;
    } elseif ($hasStudentId) {
        $searchParts[] = "s.student_id LIKE ?";
        $params[] = $searchWildcard;
    }
    $query .= " AND (" . implode(' OR ', $searchParts) . ")";
}

$query .= " ORDER BY sec.grade_level, sec.section_name, s.last_name, s.first_name";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $students = [];
    error_log("Students directory error: " . $e->getMessage());
}

// Group students by section
$studentsBySection = [];
foreach ($students as $student) {
    $sectionKey = $student['section_id'] ?? 'unassigned';
    $sectionName = $student['section_name'] ?? 'Unassigned';
    $gradeLevel = $student['grade_level'] ?? '';
    
    if (!isset($studentsBySection[$sectionKey])) {
        $studentsBySection[$sectionKey] = [
            'name' => $sectionName,
            'grade_level' => $gradeLevel,
            'students' => [],
            'male_count' => 0,
            'female_count' => 0
        ];
    }
    
    $studentsBySection[$sectionKey]['students'][] = $student;
    
    // Count by sex
    $sex = $student['sex'] ?? $student['gender'] ?? '';
    if (strtolower($sex) === 'male') {
        $studentsBySection[$sectionKey]['male_count']++;
    } else {
        $studentsBySection[$sectionKey]['female_count']++;
    }
}

// Calculate totals
$totalStudents = count($students);
$totalMale = array_sum(array_column($studentsBySection, 'male_count'));
$totalFemale = array_sum(array_column($studentsBySection, 'female_count'));

include 'includes/header_modern.php';
?>

<!-- Page Header - Enhanced Design -->
<div class="page-header-enhanced">
    <div class="page-header-background">
        <div class="header-gradient-overlay"></div>
        <div class="header-pattern"></div>
    </div>
    <div class="page-header-content-enhanced">
        <div class="page-title-section">
            <div class="page-icon-enhanced">
                <i class="fas fa-<?php echo $pageIcon; ?>"></i>
            </div>
            <div class="page-title-content">
                <div class="breadcrumb-nav">
                    <a href="dashboard.php" class="breadcrumb-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <i class="fas fa-chevron-right breadcrumb-separator"></i>
                    <span class="breadcrumb-current"><?php echo $pageTitle; ?></span>
                </div>
                <h1 class="page-title-enhanced"><?php echo $pageTitle; ?></h1>
                <p class="page-subtitle-enhanced">
                    <i class="fas fa-info-circle"></i>
                    <span>Browse students organized by grade level and section</span>
                </p>
            </div>
        </div>
        <div class="page-actions-enhanced">
            <?php if (hasPermission('student_manage')): ?>
            <a href="manage_students.php" class="btn-header btn-header-primary">
                <i class="fas fa-user-plus"></i>
                <span>Manage Students</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Info Alert -->
<div class="alert alert-info">
    <div class="alert-icon">
        <i class="fas fa-info-circle"></i>
    </div>
    <div class="alert-content">
        <strong>Students Directory</strong>
        <p style="margin: var(--space-1) 0 0; line-height: 1.6;">
            Browse and search through all enrolled students. Filter by grade level, section, or search by name. Click on a student card to view their details.
        </p>
    </div>
</div>

<div class="content-wrapper">
    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $totalStudents; ?></span>
                <span class="stat-label">Total Students</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-mars"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $totalMale; ?></span>
                <span class="stat-label">Male</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pink">
                <i class="fas fa-venus"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo $totalFemale; ?></span>
                <span class="stat-label">Female</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-chalkboard"></i>
            </div>
            <div class="stat-info">
                <span class="stat-value"><?php echo count($studentsBySection); ?></span>
                <span class="stat-label">Sections</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div id="filtersPanel" class="filters-card" style="<?php echo ($filterGradeLevel || $filterSection || $filterSex || $searchTerm) ? '' : 'display:none;'; ?>">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label for="grade_level">Grade Level</label>
                <select id="grade_level" name="grade_level" onchange="this.form.submit()">
                    <option value="">All Grade Levels</option>
                    <?php foreach ($gradeLevels as $grade): ?>
                        <option value="<?php echo htmlspecialchars($grade); ?>" 
                            <?php echo $filterGradeLevel === $grade ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($grade); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="section">Section</label>
                <select id="section" name="section">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?php echo $sec['id']; ?>" 
                            <?php echo $filterSection == $sec['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sec['section_name']); ?>
                            <?php if (!$filterGradeLevel): ?>
                                (<?php echo htmlspecialchars($sec['grade_level']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="sex">Sex</label>
                <select id="sex" name="sex">
                    <option value="">All</option>
                    <option value="Male" <?php echo $filterSex === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $filterSex === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" 
                       value="<?php echo htmlspecialchars($searchTerm); ?>"
                       placeholder="Name or Student ID">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Apply
                </button>
                <a href="students_directory.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Students by Section -->
    <?php if (empty($studentsBySection)): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>No Students Found</h3>
            <p>No students match your current filters. Try adjusting your search criteria.</p>
        </div>
    <?php else: ?>
        <?php foreach ($studentsBySection as $sectionKey => $sectionData): ?>
            <div class="section-card">
                <div class="section-header" onclick="toggleSection('section-<?php echo $sectionKey; ?>')">
                    <div class="section-info">
                        <h3>
                            <i class="fas fa-chalkboard-teacher"></i>
                            <?php echo htmlspecialchars($sectionData['name']); ?>
                            <?php if ($sectionData['grade_level']): ?>
                                <span class="grade-badge"><?php echo htmlspecialchars($sectionData['grade_level']); ?></span>
                            <?php endif; ?>
                        </h3>
                        <div class="section-stats">
                            <span class="stat-pill">
                                <i class="fas fa-users"></i> <?php echo count($sectionData['students']); ?> students
                            </span>
                            <span class="stat-pill male">
                                <i class="fas fa-mars"></i> <?php echo $sectionData['male_count']; ?>
                            </span>
                            <span class="stat-pill female">
                                <i class="fas fa-venus"></i> <?php echo $sectionData['female_count']; ?>
                            </span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-down section-toggle"></i>
                </div>
                
                <div id="section-<?php echo $sectionKey; ?>" class="section-content">
                    <div class="students-grid">
                        <?php foreach ($sectionData['students'] as $student): ?>
                            <div class="student-card">
                                <div class="student-avatar">
                                    <?php 
                                    $sex = $student['sex'] ?? $student['gender'] ?? 'Male';
                                    $avatarIcon = (strtolower($sex) === 'female') ? 'fa-user-female' : 'fa-user';
                                    $avatarClass = (strtolower($sex) === 'female') ? 'female' : 'male';
                                    ?>
                                    <i class="fas <?php echo $avatarIcon; ?> <?php echo $avatarClass; ?>"></i>
                                </div>
                                <div class="student-info">
                                    <h4>
                                        <?php 
                                        $fullName = $student['first_name'];
                                        if (!empty($student['middle_name'])) {
                                            $fullName .= ' ' . substr($student['middle_name'], 0, 1) . '.';
                                        }
                                        $fullName .= ' ' . $student['last_name'];
                                        echo htmlspecialchars($fullName);
                                        ?>
                                    </h4>
                                    <?php
                                    $displayId = $student['lrn'] ?? $student['student_id'] ?? $student['id'] ?? '';
                                    $monthly = isset($student['monthly_attendance']) ? (int)$student['monthly_attendance'] : 0;
                                    $lastAtt = $student['last_attendance'] ?? null;
                                    ?>
                                    <div class="student-id">ID: <?php echo htmlspecialchars($displayId); ?></div>
                                    <div class="student-meta">
                                        <?php if ($lastAtt): ?>
                                            <span class="last-seen" title="Last Attendance">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('M d', strtotime($lastAtt)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="monthly-attendance" title="This Month's Attendance">
                                            <i class="fas fa-calendar-check"></i>
                                            <?php echo $monthly; ?> days
                                        </span>
                                    </div>
                                </div>
                                <div class="student-actions">
                                    <?php
                                    // Safe edit link id (prefer numeric id if present)
                                    $editId = $student['id'] ?? $student['student_id'] ?? '';
                                    // Determine identifier for details modal
                                    if ($hasLrn && !empty($student['lrn'])) {
                                        $idType = 'lrn';
                                        $idValue = $student['lrn'];
                                    } elseif (!empty($student['student_id'])) {
                                        $idType = 'student_id';
                                        $idValue = $student['student_id'];
                                    } elseif (!empty($student['id'])) {
                                        $idType = 'id';
                                        $idValue = $student['id'];
                                    } else {
                                        $idType = 'id';
                                        $idValue = '';
                                    }
                                    $escapedId = htmlspecialchars($idValue, ENT_QUOTES);
                                    ?>
                                    <?php if (hasPermission('student_manage')): ?>
                                    <a href="manage_students.php?id=<?php echo htmlspecialchars($editId); ?>" 
                                       class="btn btn-sm btn-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button class="btn-icon" title="View Details" onclick="viewStudentDetails('<?php echo $escapedId; ?>','<?php echo $idType; ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Student Details Modal -->
<div id="studentModal" class="modal" style="display:none;">
    <div class="modal-content modal-lg">
        <span class="close" onclick="closeStudentModal()">&times;</span>
        <div id="studentModalContent">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.alert.alert-info {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}

.alert.alert-info .alert-icon {
    flex: 0 0 auto;
    margin-top: 0.125rem;
}

.alert.alert-info .alert-content {
    flex: 1 1 auto;
    min-width: 0;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--asj-green-100);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
}

.stat-icon.blue { background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%); }
.stat-icon.green { background: linear-gradient(135deg, #16a34a 0%, #0f766e 100%); }
.stat-icon.pink { background: linear-gradient(135deg, #22c55e 0%, #14b8a6 100%); }
.stat-icon.purple { background: linear-gradient(135deg, var(--asj-green-400) 0%, var(--asj-green-600) 100%); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
}

.stat-label {
    font-size: 0.875rem;
    color: #666;
}

.filters-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #555;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9375rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.section-card {
    background: #fff;
    border-radius: 12px;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    border: 1px solid var(--asj-green-100);
}

.section-header {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    background: linear-gradient(135deg, var(--asj-green-500) 0%, var(--asj-green-700) 100%);
    color: #fff;
    transition: background 0.3s ease;
}

.section-header:hover {
    background: linear-gradient(135deg, var(--asj-green-600) 0%, var(--asj-green-800, #2E7D32) 100%);
}

.section-header h3 {
    margin: 0;
    font-size: 1.125rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.grade-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.section-stats {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.stat-pill {
    background: rgba(255,255,255,0.2);
    padding: 0.25rem 0.625rem;
    border-radius: 20px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.stat-pill.male { background: rgba(30, 144, 255, 0.3); }
.stat-pill.female { background: rgba(255, 105, 180, 0.3); }

.section-toggle {
    transition: transform 0.3s ease;
}

.section-card.collapsed .section-toggle {
    transform: rotate(-90deg);
}

.section-content {
    padding: 1.5rem;
    border-top: 1px solid #eee;
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.student-card {
    background: #fff;
    border-radius: 10px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--asj-green-100);
    border-left: 4px solid var(--asj-green-500);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(20, 83, 45, 0.15);
    border-color: var(--asj-green-300);
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: var(--asj-green-50);
    border: 1px solid var(--asj-green-200);
    color: var(--asj-green-700);
}

.student-avatar .male {
    color: var(--asj-green-700);
}

.student-avatar .female {
    color: #d9468f;
}

.student-info {
    flex: 1;
}

.student-info h4 {
    margin: 0;
    font-size: 0.9375rem;
    color: #333;
}

.student-id {
    margin: 0.25rem 0;
    font-size: 0.8125rem;
    color: #666;
}

.student-meta {
    display: flex;
    gap: 0.75rem;
    font-size: 0.75rem;
    color: #888;
    flex-wrap: wrap;
}

.student-meta i {
    margin-right: 0.25rem;
}

.student-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    background: var(--asj-green-50);
    color: var(--asj-green-700);
    border: 1px solid var(--asj-green-100);
}

.student-actions {
    display: flex;
    gap: 0.375rem;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--asj-green-50);
    border: 1px solid var(--asj-green-200);
    color: var(--asj-green-700);
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: var(--asj-green-600);
    border-color: var(--asj-green-600);
    color: #fff;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}

.empty-state i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #666;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #999;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    padding: 2rem;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
    border: 1px solid var(--asj-green-100);
    box-shadow: 0 18px 40px rgba(20, 83, 45, 0.15);
}

.modal-lg {
    max-width: 800px;
}

.close {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: var(--asj-green-600);
}

.close:hover {
    color: var(--asj-green-800);
}

.loading {
    text-align: center;
    padding: 2rem;
    color: var(--asj-green-700);
}

#studentModal h2 {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin: 0 0 1rem;
    color: var(--asj-green-800);
    font-size: 1.5rem;
}

#studentModal h2 i {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--asj-green-50);
    color: var(--asj-green-600);
    border: 1px solid var(--asj-green-100);
}

.student-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
    margin-top: 0.75rem;
}

.detail-section {
    background: var(--asj-green-50);
    border: 1px solid var(--asj-green-100);
    border-radius: 12px;
    padding: 1rem;
}

.detail-section h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.75rem;
    color: var(--asj-green-800);
    font-size: 1rem;
    font-weight: 700;
}

.detail-section h4 i {
    color: var(--asj-green-600);
}

.detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px dashed var(--asj-green-200);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--asj-green-800);
}

.detail-label i {
    color: var(--asj-green-600);
}

.detail-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f2937;
    text-align: right;
}

.qr-section {
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 12px;
    background: var(--asj-green-50);
    border: 1px dashed var(--asj-green-200);
    text-align: center;
}

.qr-section h4 {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin: 0 0 0.75rem;
    color: var(--asj-green-800);
}

.qr-section h4 i {
    color: var(--asj-green-600);
}

.qr-section img {
    max-width: 150px;
    border: 2px solid var(--asj-green-300);
    border-radius: 10px;
    padding: 6px;
    background: #fff;
}

#studentModal .error {
    padding: 0.75rem 1rem;
    border-radius: 10px;
    background: var(--asj-green-50);
    border: 1px solid var(--asj-green-200);
    color: var(--asj-green-800);
    font-weight: 600;
    text-align: center;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filters-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .students-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function toggleFilters() {
    const panel = document.getElementById('filtersPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function toggleSection(sectionId) {
    const content = document.getElementById(sectionId);
    const card = content.closest('.section-card');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        card.classList.remove('collapsed');
    } else {
        content.style.display = 'none';
        card.classList.add('collapsed');
    }
}

function viewStudentDetails(idValue, idType) {
    const modal = document.getElementById('studentModal');
    const content = document.getElementById('studentModalContent');

    modal.style.display = 'flex';
    content.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    if (!idValue) {
        content.innerHTML = '<p class="error">Student identifier missing.</p>';
        return;
    }

    // build URL based on identifier type (prefer lrn when available)
    const params = new URLSearchParams();
    if (idType === 'lrn') {
        params.set('lrn', idValue);
    } else {
        params.set('id', idValue);
    }
    params.set('id_type', idType);
    const url = '../api/get_student_details.php?' + params.toString();

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.student;
                const sex = student.sex || student.gender || 'N/A';
                const mobile = student.mobile_number || student.email || 'N/A';

                content.innerHTML = `
                    <h2><i class="fas fa-user-graduate"></i> Student Details</h2>
                    <div class="student-detail-grid">
                        <div class="detail-section">
                            <h4><i class="fas fa-id-card"></i> Personal Information</h4>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-hashtag"></i> Student ID:</span>
                                <span class="detail-value">${student.lrn || student.student_id || student.id || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-user"></i> Full Name:</span>
                                <span class="detail-value">${student.first_name} ${student.middle_name || ''} ${student.last_name}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-venus-mars"></i> Sex:</span>
                                <span class="detail-value">${sex}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-phone"></i> Contact:</span>
                                <span class="detail-value">${mobile}</span>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4><i class="fas fa-book"></i> Academic Information</h4>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-layer-group"></i> Section:</span>
                                <span class="detail-value">${student.section_name || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label"><i class="fas fa-stream"></i> Grade Level:</span>
                                <span class="detail-value">${student.grade_level || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    ${student.qr_code ? `
                        <div class="qr-section">
                            <h4><i class="fas fa-qrcode"></i> QR Code</h4>
                            <img src="../${student.qr_code}" alt="QR Code">
                        </div>
                    ` : ''}
                `;
            } else {
                content.innerHTML = '<p class="error">Failed to load student details.</p>';
            }
        })
        .catch(error => {
            content.innerHTML = '<p class="error">Error loading student details.</p>';
            console.error('Error:', error);
        });
}

function closeStudentModal() {
    document.getElementById('studentModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('studentModal');
    if (event.target === modal) {
        closeStudentModal();
    }
}
</script>

<?php include 'includes/footer_modern.php'; ?>
