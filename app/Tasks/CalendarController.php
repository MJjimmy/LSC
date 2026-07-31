<?php

declare(strict_types=1);

namespace LSC\Tasks;

use LSC\Core\Auth;
use LSC\Groups\GroupRepository;

final class CalendarController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly TaskRepository $tasks,
        private readonly GroupRepository $groups
    ) {
    }

    public function index(): void
    {
        $this->auth->requireLogin();
        $month = (int)($_GET['month'] ?? date('n'));
        $year = max(2020, min(2099, (int)($_GET['year'] ?? date('Y'))));
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $calendar = array_fill(0, (int)date('w', $firstDay), null);
        for ($day = 1; $day <= (int)date('t', $firstDay); $day++) { $calendar[] = $day; }
        $groupIds = array_column($this->groups->forUser((int)$_SESSION['user_id']), 'group_id');
        $tasksByDay = [];
        foreach ($this->tasks->forCalendar($groupIds, $month, $year) as $task) {
            $tasksByDay[(int)date('j', strtotime($task['due_date']))][] = $task;
        }
        $today = (int)date('j');
        $isTodayMonth = $month === (int)date('n') && $year === (int)date('Y');
        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        ob_start();
        require \LSC_ROOT . '/views/tasks/calendar.php';
    }
}
