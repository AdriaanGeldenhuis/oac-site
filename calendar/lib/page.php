<?php
declare(strict_types=1);
/**
 * Calendar pages (calendar.php and view.php) share their head, icons,
 * atmosphere and the strings the script needs. Kept here so both pages
 * look and speak exactly the same.
 */

require_once __DIR__ . '/calendar.php';

function cal_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cache-bust by file time, so browsers keep the assets until they change */
function cal_asset(string $path): string {
    $mtime = @filemtime(dirname(__DIR__, 2) . $path);
    return $path . '?v=' . ($mtime ?: '1');
}

/** Icons used by the page and by calendar.js (<use href="#cal-i-…">) */
function cal_icons(): array {
    return [
        'calendar' => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>',
        'event' => '<path d="M18 12.22V9l-5-2.5V5h2V3h-2V1h-2v2H9v2h2v1.5L6 9v3.22L2 14v8h8v-3c0-1.1.9-2 2-2s2 .9 2 2v3h8v-8l-4-1.78zm-6 1.28c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
        'visit' => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
        'appointment' => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
        'diary' => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
        'birthday' => '<path d="M12 6c1.11 0 2-.9 2-2 0-.38-.1-.73-.29-1.03L12 0l-1.71 2.97c-.19.3-.29.65-.29 1.03 0 1.1.9 2 2 2zm4.6 9.99l-1.07-1.07-1.08 1.07c-1.3 1.3-3.58 1.31-4.89 0l-1.07-1.07-1.09 1.07C6.75 16.64 5.88 17 4.96 17c-.73 0-1.4-.23-1.96-.61V21c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-4.61c-.56.38-1.23.61-1.96.61-.92 0-1.79-.36-2.44-1.01zM18 9h-5V7h-2v2H6c-1.66 0-3 1.34-3 3v1.54c0 1.08.88 1.96 1.96 1.96.52 0 1.02-.2 1.38-.57l2.14-2.13 2.13 2.13c.74.74 2.03.74 2.77 0l2.14-2.13 2.13 2.13c.37.37.86.57 1.38.57 1.08 0 1.96-.88 1.96-1.96V12C21 10.34 19.66 9 18 9z"/>',
        'clock' => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>',
        'pin' => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
        'person' => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'room' => '<path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12zM10 15l5.5-4.5L10 6v9z"/>',
        'phone' => '<path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14zm-4.2-5.78v1.75l3.2-2.99L12.8 9v1.7c-3.11.43-4.35 2.56-4.8 4.7 1.11-1.5 2.58-2.18 4.8-2.18z"/>',
        'bell' => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
        'share' => '<path d="M16.5 13c-1.2 0-3.07.34-4.5 1-1.43-.67-3.3-1-4.5-1C5.33 13 1 14.08 1 16.25V19h22v-2.75c0-2.17-4.33-3.25-6.5-3.25zm-4 4.5h-10v-1.25c0-.54 2.56-1.75 5-1.75s5 1.21 5 1.75v1.25zm9 0H14v-1.25c0-.46-.2-.86-.52-1.22.88-.3 1.96-.53 3.02-.53 2.44 0 5 1.21 5 1.75v1.25zM7.5 12c1.93 0 3.5-1.57 3.5-3.5S9.43 5 7.5 5 4 6.57 4 8.5 5.57 12 7.5 12zm0-5.5c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 5.5c1.93 0 3.5-1.57 3.5-3.5S18.43 5 16.5 5 13 6.57 13 8.5s1.57 3.5 3.5 3.5zm0-5.5c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>',
        'notes' => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
        'pdf' => '<path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/>',
        'sheet' => '<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-2h2v2zm0-4H7v-2h2v2zm0-4H7V7h2v2zm8 8h-6v-2h6v2zm0-4h-6v-2h6v2zm0-4h-6V7h6v2z"/>',
    ];
}

function cal_render_sprite(): void {
    echo '<svg class="cal-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>';
    foreach (cal_icons() as $id => $path) {
        echo '<symbol id="cal-i-' . $id . '" viewBox="0 0 24 24" fill="currentColor">' . $path . '</symbol>';
    }
    echo '</defs></svg>';
}

function cal_render_head(string $title): void {
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= cal_e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@1,500&family=Parisienne&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= cal_asset('/calendar/css/calendar.css') ?>">
    <?php
}

function cal_render_aura(): void {
    echo '<div class="cal-aura" aria-hidden="true"><div class="cal-rays"></div><div class="cal-halo"></div></div>';
}

/** Everything calendar.js says, in the page language */
function cal_js_strings(string $lang): array {
    $keys = [
        'day', 'week', 'month', 'today', 'tomorrow', 'event', 'diary', 'appointment', 'title', 'date', 'time',
        'room', 'select_room', 'location', 'notes', 'share_with_spouse', 'cancel', 'delete', 'save', 'edit',
        'close', 'loading', 'name', 'type_name', 'select_user', 'with_whom', 'saved_success', 'deleted_success',
        'pdf', 'excel', 'profile', 'calendar',
        'cal_visit', 'cal_birthday', 'cal_f_events', 'cal_f_visits', 'cal_f_appointments', 'cal_f_birthdays',
        'cal_show', 'cal_new', 'cal_add', 'cal_new_event', 'cal_new_event_hint', 'cal_new_visit', 'cal_new_visit_hint',
        'cal_new_diary', 'cal_new_diary_hint', 'cal_edit_event', 'cal_edit_visit', 'cal_all_day', 'cal_add_to_phone',
        'cal_send_reminder', 'cal_reminder_sent', 'cal_reminder_recent', 'cal_open_in_gospel', 'cal_open_in_diary',
        'cal_confirm', 'cal_cancel_appt', 'cal_cancel_appt_q', 'cal_cancel_appt_text', 'cal_appt_confirmed',
        'cal_appt_cancelled', 'cal_status_pending', 'cal_status_confirmed', 'cal_birthday_of', 'cal_your_birthday',
        'cal_shared_by_spouse', 'cal_shared_with', 'cal_read_only', 'cal_nothing_day', 'cal_nothing_period',
        'cal_empty_hint', 'cal_load_failed', 'cal_retry', 'cal_delete_q', 'cal_delete_event_q', 'cal_delete_event_text',
        'cal_cannot_undo', 'cal_err_title', 'cal_err_date', 'cal_err_time', 'cal_err_time_event', 'cal_err_room',
        'cal_err_person', 'cal_err_forbidden', 'cal_err_session', 'cal_err_generic', 'cal_err_rate', 'cal_err_not_found',
        'cal_err_offline', 'cal_err_past', 'cal_err_already', 'cal_err_no_spouse', 'cal_items', 'cal_one_item',
        'cal_more', 'cal_previous', 'cal_next', 'cal_view', 'cal_exporting', 'cal_make_appointment', 'cal_spouse_pending',
        'cal_spouse_view', 'cal_no_rooms', 'cal_when', 'cal_by', 'cal_ics_ready', 'cal_week_n', 'cal_saving',
        'cal_person', 'cal_from_list', 'cal_title_visit_ph', 'cal_title_event_ph', 'cal_room_event_note', 'cal_status',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __t($k, $lang);
    }
    $out['days'] = array_map(fn($k) => __t($k, $lang), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
    $out['days_short'] = array_map(fn($k) => __t($k, $lang), ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
    $out['months'] = array_map(fn($k) => __t($k, $lang), ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']);
    return $out;
}

function cal_json_for_script($data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}

/** Toolbar, filters, board and agenda: the same on both pages */
function cal_render_board(array $T, bool $canAdd): void {
    ?>
    <section class="cal-card cal-toolbar" aria-label="<?= cal_e($T['cal_view']) ?>">
        <div class="cal-nav">
            <button class="cal-icon-btn" id="calPrev" type="button" aria-label="<?= cal_e($T['cal_previous']) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button class="cal-today-btn" id="calToday" type="button"><?= cal_e($T['today']) ?></button>
            <button class="cal-icon-btn" id="calNext" type="button" aria-label="<?= cal_e($T['cal_next']) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>
        <div class="cal-seg" role="group" aria-label="<?= cal_e($T['cal_view']) ?>">
            <button class="cal-seg-btn" type="button" data-view="month" aria-pressed="true"><?= cal_e($T['month']) ?></button>
            <button class="cal-seg-btn" type="button" data-view="week" aria-pressed="false"><?= cal_e($T['week']) ?></button>
            <button class="cal-seg-btn" type="button" data-view="day" aria-pressed="false"><?= cal_e($T['day']) ?></button>
        </div>
        <?php if ($canAdd): ?>
        <button class="cal-btn cal-btn--primary cal-new-btn" id="calNew" type="button" aria-haspopup="dialog">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            <span><?= cal_e($T['cal_new']) ?></span>
        </button>
        <?php endif; ?>
    </section>

    <div class="cal-filters" id="calFilters" role="group" aria-label="<?= cal_e($T['cal_show']) ?>"></div>

    <section class="cal-card cal-board" id="calBoard" aria-busy="true">
        <div class="cal-board-inner" id="calBoardInner">
            <div class="cal-skel" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
            <p class="cal-sr"><?= cal_e($T['loading']) ?></p>
        </div>
    </section>

    <section class="cal-agenda" id="calAgenda" aria-labelledby="calAgendaTitle">
        <div class="cal-agenda-head">
            <h3 class="cal-label" id="calAgendaTitle"></h3>
            <span class="cal-agenda-count" id="calAgendaCount"></span>
        </div>
        <div class="cal-list" id="calList" aria-live="polite"></div>
    </section>
    <?php
}
