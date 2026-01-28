<?php
// src/student/instructions.php
require_once '../middleware/auth.php';
if ($_SESSION['user']['role'] !== 'student') { die('Forbidden'); }
require_once '../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) { die('Missing exam id'); }


$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.duration,
        e.total_marks,
        e.status,
        c.title   AS course_title,
        c.description AS course_description
    FROM exams e
    LEFT JOIN courses c ON e.course_id = c.id
    WHERE e.id = ?
    LIMIT 1
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die('Exam not found');
}

// Optional: only allow active exams
if ($exam['status'] !== 'in_progress') {
    die('This exam is not currently available.');
}

/* ---- Instructions text (kept as a single source for easy editing) ---- */
$instructions = <<<'TXT'
INSTRUCTIONS TO STUDENTS
1. Candidates must attend a minimum of 65% of the lectures for the course. Examiners have the right to prevent defaulters from sitting for the examinations.
2. Candidates must be ready to enter the examination hall ten minutes before the commencement of examination. Candidates who arrive more than half an hour after the commencement of an examination shall be admitted only at the discretion of the invigilator(s).
3. Candidates may not leave the hall during the examination.
4. Candidates must come with their own writing materials to the examination hall.
5. All rough work must be done in the answer booklets and crossed neatly throughout.
6. Communication between candidates is strictly forbidden.
7. The only permissible way of attracting attention of the invigilator is by raising of hand.
8. Candidates are to write legibly. Names are not to be written on answer booklets. The answers to each question must be started on a separate page.
9. Attendance register is to be signed at the commencement of the examination and as each candidate hands in the script to the invigilator.
10. Candidates must ensure that they have inserted at the appropriate places on the front of their booklets, their examination numbers and the questions they answered.
11. Mobile phones are not allowed in the examination hall.
12. If the equipment is inactive (in off position), the invigilator should return the seized equipment to the student after conclusion of the examination.

SNo Misconduct — Penalty
1. Impersonation — Expulsion
2. Smuggling and Possession of Answer Script — Expulsion
3. Destruction of Unauthorized Materials — Expulsion
4. Attacking or threatening Invigilators — Expulsion
5. Tendering unauthentic document — Expulsion
6. Failure to submit answer script — Rustication (2 semesters)
7. Recidivism — Expulsion (Except cases listed in 15-19 below)
8. Copying from unauthorized materials — Rustication (2 semesters)
9. Possession of writing materials
   - 1st Time — Rustication (2 semesters)
   - Recidivism — Expulsion
10. Aiding and Abetting others to copy — Rustication (2 semesters)
11. Refusal to submit offending materials — Rustication (2 semesters)
12. Collaborative copying — Rustication (2 semesters)
13. Refusal to complete examination misconduct forms — Rustication (2 semesters)
14. Unauthorized communication
   - 1st Time — warning
   - 2nd Time — Rustication (1 semester)
15. Disruptive behaviour
   - 1st Time — warning
   - 2nd Time — Rustication (1 semester)
16. Influencing examination official
   - 1st Time — warning
   - 2nd Time — Rustication (1 semester)
17. Unauthorised changing of sitting position
   - 1st Time — warning
   - 2nd Time — Rustication (1 semester)
18. Disobeying examination instructions
   - 1st Time — warning
   - 2nd Time — Rustication (1 semester)
19. Possession of telephone(s) in the examination hall either in use or not — Rustication (1 semester)

HARMONIZED PENALTIES/SANCTIONS FOR EXAMINATION MISCONDUCT
20. Failure to appear before the Misconduct Panel — *Suspension for 2 semesters; non-appearance leads to Expulsion
21. Other related act of Examination Misconduct not specifically stated — *Penalty determined by the Misconduct Panel

TECH / ONLINE-AGE MISCONDUCT (ADDITIONAL)
1. Screen sharing / Mirroring to other devices / Projectors of friends/classmates/family/experts to cheat — Rustication (2 semesters)
2. Cheating with Technological Devices/High-Tech Equipment (e.g. micro Bluetooth devices, augmented reality glasses, invisible smart watches, hard drives, USB) — Rustication (2 semesters)
3. Smartphones / Smart devices and use of mobile education apps to retrieve automated recommended answers — Rustication (2 semesters)
4. Impersonation or Faking of Identities — Expulsion
5. Auto-coding software & remote control software (e.g. TeamViewer) to allow others control of a candidate's device — Expulsion
6. Plagiarism of content — Expulsion
7. Hacking of question bank/system resulting in leakage of questions — Expulsion
8. Deliberate obstruction of proctoring device — Rustication (2 semesters)
9. Presence of family/friends in the examination room — Rustication (2 semesters); Recidivism: Expulsion
10. Traditional jottings of relevant materials on body parts / devices — Rustication (2 semesters)
11. Other online-related misconduct not listed — Penalty determined by the Misconduct Panel
TXT;

?>
<?php require '../constants/header.php' ?>
<title>Instructions — <?= htmlspecialchars($exam['course_title'] ?? 'Exam') ?></title>
</head>

<style>
    .body {
        background: #042c2c;
    }
</style>

<body class="body container py-4">      
        
        <!-- NOTE: guidance / online consideration -->
        <div class="alert alert-info">
            <h3><?= htmlspecialchars($exam['course_title'] ?? 'Exam') ?></h3>
            
            <p class="text-muted">
                <?= nl2br(htmlspecialchars($exam['course_description'] ?? 'No description available.')) ?>
            </p>
            
            <div class="mb-3">
                <ul>
                    <li>Duration: <strong><?= (int)$exam['duration'] ?> minutes</strong></li>
                    <li>Total marks: <strong><?= (int)$exam['total_marks'] ?></strong></li>
                    <li>Do not refresh the page — answers are autosaved.</li>
                </ul>
            </div>
            <strong>Note:</strong><p class="text-danger">The text below is a guide covering standard examination rules and harmonized penalties. It has been adapted to include online/remote exam considerations (for example: proctoring, device usage and screen-sharing).
            Local exam officers and the institution retain final authority — this page is informational and does not replace official policy.

            </p> 
        </div>

<!-- Full instructions -->
<div class="card mb-3">
    <div class="card-body" style="white-space:pre-line; font-size:0.95rem;">
        <?= nl2br(htmlspecialchars($instructions)) ?>
    </div>
</div>

<form method="post" action="../api/start_attempt.php">
    <input type="hidden" name="exam_id" value="<?= (int)$exam_id ?>">
    <button class="btn btn-primary">Start Exam</button>
    <a href="dashboard.php" class="btn btn-secondary">Back</a>
</form>

</body>
</html>
