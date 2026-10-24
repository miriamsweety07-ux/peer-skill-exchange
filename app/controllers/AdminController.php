<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Skill.php';
require_once __DIR__ . '/../models/Session.php';
require_once __DIR__ . '/../models/MemberSkill.php';

// Super Admin oversight. Every query on this controller's path is a
// count/join built entirely from users/skills/sessions.status -- none of
// them ever selects messages.body or sessions.notes (FEATURES.md §3
// "Chat privacy is structural" applies to the admin side too, not just
// Member-vs-Member).
class AdminController
{
    public function dashboard(): void
    {
        Auth::requireRole('super_admin');

        $pageTitle = 'Admin Dashboard';
        $activeMembers = User::countActiveMembers();
        $totalMembers = User::countAllMembers();
        $sessionCounts = Session::countByStatus();
        $totalSessions = Session::totalCount();
        $topSkills = Skill::mostRequested(5);

        require __DIR__ . '/../views/admin/dashboard.php';
    }

    public function members(): void
    {
        Auth::requireRole('super_admin');

        $pageTitle = 'Manage Members';
        $members = User::allMembers();

        require __DIR__ . '/../views/admin/members.php';
    }

    public function memberStatus(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');

        if (!in_array($status, ['active', 'inactive'], true)) {
            flash_set('error', 'Invalid status.');
            redirect('admin/members');
        }

        // Deactivation is never blocked (an admin must always be able to
        // pull a bad actor off the platform), but it does have a real
        // knock-on effect: the other participant in every live session of
        // theirs is left holding a booking that can no longer be confirmed.
        // Name that count instead of flipping the flag silently.
        $live = $status === 'inactive' ? Session::liveCountForUser($id) : 0;

        if (User::setStatus($id, $status)) {
            if ($live > 0) {
                flash_set('success', 'Member deactivated. Note: ' . $live . ' pending or confirmed session' . ($live === 1 ? '' : 's')
                    . ' involving this member can no longer be confirmed — the other participants are now shown a notice on those sessions.');
            } else {
                flash_set('success', 'Member status updated.');
            }
        } else {
            flash_set('error', 'Member not found.');
        }

        redirect('admin/members');
    }

    public function skills(): void
    {
        Auth::requireRole('super_admin');

        $pageTitle = 'Skills Taxonomy';
        $skills = Skill::all();

        require __DIR__ . '/../views/admin/skills.php';
    }

    public function skillStore(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        [$data, $errors] = $this->validateSkill($_POST);

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/skills');
        }

        if (Skill::nameExists($data['name'])) {
            flash_set('error', 'A skill with that name already exists.');
            redirect('admin/skills');
        }

        Skill::create($data['name'], $data['category']);
        flash_set('success', 'Skill added to the taxonomy.');
        redirect('admin/skills');
    }

    public function skillUpdate(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $skill = Skill::find($id);
        if (!$skill) {
            flash_set('error', 'Skill not found.');
            redirect('admin/skills');
        }

        [$data, $errors] = $this->validateSkill($_POST);

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            redirect('admin/skills');
        }

        if (Skill::nameExists($data['name'], $id)) {
            flash_set('error', 'A skill with that name already exists.');
            redirect('admin/skills');
        }

        Skill::update($id, $data['name'], $data['category']);
        flash_set('success', 'Skill updated.');
        redirect('admin/skills');
    }

    public function skillToggle(): void
    {
        Auth::requireRole('super_admin');
        Csrf::requireValid();

        $id = (int)($_POST['id'] ?? 0);
        $skill = Skill::find($id);
        if (!$skill) {
            flash_set('error', 'Skill not found.');
            redirect('admin/skills');
        }

        Skill::setActive($id, !$skill['is_active']);
        flash_set('success', $skill['is_active'] ? 'Skill deactivated.' : 'Skill re-activated.');
        redirect('admin/skills');
    }

    private function validateSkill(array $post): array
    {
        $v = (new Validator())
            ->required($post, 'name', 'Skill name')
            ->maxLength($post, 'name', 100, 'Skill name')
            ->required($post, 'category', 'Category')
            ->maxLength($post, 'category', 60, 'Category');

        $data = [
            'name'     => trim((string)($post['name'] ?? '')),
            'category' => trim((string)($post['category'] ?? '')),
        ];

        return [$data, array_values($v->errors())];
    }
}
