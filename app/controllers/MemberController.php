<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Skill.php';
require_once __DIR__ . '/../models/MemberSkill.php';
require_once __DIR__ . '/../models/Session.php';

class MemberController
{
    public function dashboard(): void
    {
        Auth::requireRole('member');

        $pageTitle = 'Dashboard';
        $userId = Auth::id();

        $upcoming = Session::upcomingForUser($userId, 5);
        $pending = Session::pendingForRecipient($userId);
        $matches = array_slice(MemberSkill::matchesFor($userId), 0, 5);
        $teachCount = count(MemberSkill::forUser($userId, 'teach'));
        $learnCount = count(MemberSkill::forUser($userId, 'learn'));

        require __DIR__ . '/../views/member/dashboard.php';
    }

    public function profile(): void
    {
        Auth::requireRole('member');

        $pageTitle = 'My Skill Profile';
        $userId = Auth::id();

        $user = User::find($userId);
        $teaching = MemberSkill::forUser($userId, 'teach');
        $learning = MemberSkill::forUser($userId, 'learn');
        $skills = Skill::allActive();
        $levels = MemberSkill::levels();
        $lockedTeachSkills = Session::activeCountsByTeachSkill($userId);

        require __DIR__ . '/../views/member/profile.php';
    }

    public function profileBio(): void
    {
        Auth::requireRole('member');
        Csrf::requireValid();

        $bio = trim((string)($_POST['bio'] ?? ''));
        if (strlen($bio) > 500) {
            flash_set('error', 'Bio must be at most 500 characters.');
            redirect('member/profile');
        }

        User::updateBio(Auth::id(), $bio !== '' ? $bio : null);
        flash_set('success', 'Bio updated.');
        redirect('member/profile');
    }

    public function skillAdd(): void
    {
        Auth::requireRole('member');
        Csrf::requireValid();

        $userId = Auth::id();
        $skillId = (int)($_POST['skill_id'] ?? 0);
        $type = (string)($_POST['type'] ?? '');
        $level = (string)($_POST['proficiency_level'] ?? '');

        if (!in_array($type, MemberSkill::types(), true)) {
            flash_set('error', 'Invalid skill type.');
            redirect('member/profile');
        }

        $skill = Skill::find($skillId);
        if (!$skill || !$skill['is_active']) {
            flash_set('error', 'Choose a valid, active skill.');
            redirect('member/profile');
        }

        if ($type === 'teach' && !in_array($level, MemberSkill::levels(), true)) {
            flash_set('error', 'Choose a valid proficiency level.');
            redirect('member/profile');
        }

        if (MemberSkill::exists($userId, $skillId, $type)) {
            flash_set('error', 'That skill is already on your ' . ($type === 'teach' ? 'teaching' : 'learning') . ' list.');
            redirect('member/profile');
        }

        MemberSkill::add($userId, $skillId, $type, $type === 'teach' ? $level : null);
        flash_set('success', 'Skill added to your profile.');
        redirect('member/profile');
    }

    public function skillRemove(): void
    {
        Auth::requireRole('member');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $userId = Auth::id();

        // Server-side dependency lock, not just a hidden button: removing a
        // skill from the TEACH list while a request or a confirmed booking
        // for that exact skill is still live would leave the other member
        // holding a session for something you no longer list as teachable.
        $row = MemberSkill::findOwned($id, $userId);
        if ($row && $row['type'] === 'teach') {
            $live = Session::activeCountsByTeachSkill($userId)[(int)$row['skill_id']] ?? 0;
            if ($live > 0) {
                flash_set('error', 'You cannot remove this teaching skill: ' . $live . ' pending or confirmed session' . ($live === 1 ? '' : 's') . ' still depend' . ($live === 1 ? 's' : '') . ' on it. Complete, decline or cancel ' . ($live === 1 ? 'it' : 'them') . ' first.');
                redirect('member/profile');
            }
        }

        if (MemberSkill::deleteOwned($id, $userId)) {
            flash_set('success', 'Skill removed from your profile.');
        } else {
            flash_set('error', 'Skill entry not found, or it does not belong to you.');
        }

        redirect('member/profile');
    }

    // Combined "Skill Directory / Find a Match" screen -- a Browse tab
    // (search the taxonomy, see who teaches each skill) and a Matches tab
    // (live join of what I want to learn against what others teach),
    // both on one screen per FEATURES.md's screen list.
    public function directory(): void
    {
        Auth::requireRole('member');

        $pageTitle = 'Skill Directory';
        $userId = Auth::id();
        $tab = ($_GET['tab'] ?? 'browse') === 'matches' ? 'matches' : 'browse';
        $q = trim((string)($_GET['q'] ?? ''));

        $skills = Skill::search($q);
        $teachersBySkill = [];
        foreach ($skills as $skill) {
            $teachers = array_filter(
                MemberSkill::teachersOf((int)$skill['id']),
                fn($t) => (int)$t['user_id'] !== $userId
            );
            if ($teachers) {
                $teachersBySkill[(int)$skill['id']] = $teachers;
            }
        }

        $matches = MemberSkill::matchesFor($userId);

        require __DIR__ . '/../views/member/directory.php';
    }

    public function sessions(): void
    {
        Auth::requireRole('member');

        $pageTitle = 'My Sessions';
        $userId = Auth::id();
        $status = $_GET['status'] ?? '';
        $status = in_array($status, Session::STATUSES, true) ? $status : null;

        $sessions = Session::listForUser($userId, $status);

        require __DIR__ . '/../views/member/sessions.php';
    }
}
