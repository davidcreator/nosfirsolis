<?php

namespace Client\Controller;

use DateTimeImmutable;
use System\Library\CalendarService;

class CalendarController extends BaseController
{
    public function index(): void
    {
        $this->boot('client.calendar');

        $mode = strtolower((string) $this->request->get('mode', 'monthly'));
        if (!in_array($mode, ['annual', 'monthly', 'period'], true)) {
            $mode = 'monthly';
        }

        $year = (int) $this->request->get('year', date('Y'));
        if ($year < 1970 || $year > 2100) {
            $year = (int) date('Y');
        }

        $month = (int) $this->request->get('month', date('n'));
        $month = max(1, min(12, $month));

        $startDate = (string) $this->request->get('start_date', date('Y-m-01'));
        $endDate = (string) $this->request->get('end_date', date('Y-m-t'));
        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $filters = $this->filtersFromRequest();
        $calendarModel = $this->loader->model('calendar');
        $plannerModel = $this->loader->model('planner');
        $calendarService = new CalendarService();
        $filterData = $calendarModel->filterData();
        $userId = (int) ($this->auth->user()['id'] ?? 0);

        $annualMonths = [];
        $monthlyCalendar = null;
        $periodEvents = [];
        $visibleStart = '';
        $visibleEnd = '';

        if ($mode === 'annual') {
            $visibleStart = sprintf('%04d-01-01', $year);
            $visibleEnd = sprintf('%04d-12-31', $year);

            $notes = $plannerModel->notesByPeriod($userId, $visibleStart, $visibleEnd);
            $extraEvents = $plannerModel->extraEventsByPeriod($userId, $visibleStart, $visibleEnd);

            for ($m = 1; $m <= 12; $m++) {
                $monthStart = sprintf('%04d-%02d-01', $year, $m);
                $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
                $events = $calendarModel->eventsByPeriod($monthStart, $monthEnd, $filters);
                $this->mergeManualLayers($events, $notes, $extraEvents, $monthStart, $monthEnd);

                $annualMonths[] = $calendarService->buildMonth($year, $m, $events);
            }
        }

        if ($mode === 'monthly') {
            $visibleStart = sprintf('%04d-%02d-01', $year, $month);
            $visibleEnd = (new DateTimeImmutable($visibleStart))->modify('last day of this month')->format('Y-m-d');
            $events = $calendarModel->eventsByPeriod($visibleStart, $visibleEnd, $filters);
            $notes = $plannerModel->notesByPeriod($userId, $visibleStart, $visibleEnd);
            $extraEvents = $plannerModel->extraEventsByPeriod($userId, $visibleStart, $visibleEnd);
            $this->mergeManualLayers($events, $notes, $extraEvents, $visibleStart, $visibleEnd);

            $monthlyCalendar = $calendarService->buildMonth($year, $month, $events);
        }

        if ($mode === 'period') {
            $visibleStart = $startDate;
            $visibleEnd = $endDate;
            $periodEvents = $calendarModel->eventsByPeriod($visibleStart, $visibleEnd, $filters);
            $notes = $plannerModel->notesByPeriod($userId, $visibleStart, $visibleEnd);
            $extraEvents = $plannerModel->extraEventsByPeriod($userId, $visibleStart, $visibleEnd);
            $this->mergeManualLayers($periodEvents, $notes, $extraEvents, $visibleStart, $visibleEnd);
            ksort($periodEvents);
        }

        $flatExtraEvents = [];
        if ($visibleStart !== '' && $visibleEnd !== '') {
            $flatExtraEvents = $plannerModel->extraEventsFlatByPeriod($userId, $visibleStart, $visibleEnd);
        }

        $this->render('calendar/index', [
            'title' => $this->t('calendar.title_index', 'Calendário'),
            'mode' => $mode,
            'year' => $year,
            'month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'annual_months' => $annualMonths,
            'monthly_calendar' => $monthlyCalendar,
            'period_events' => $periodEvents,
            'filters' => $filters,
            'filter_data' => $filterData,
            'extra_events' => $flatExtraEvents,
            'calendar_colors' => $plannerModel->calendarColors($userId),
            'holiday_catalog' => $calendarModel->holidayCatalog(),
            'base_events_catalog' => $calendarModel->baseEventsCatalog($year),
            'return_query' => $this->buildReturnQuery([
                'mode' => $mode,
                'year' => (string) $year,
                'month' => (string) $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]),
            'excel_reference' => [
                'workbook' => 'Estrategia Calendário permanente 3.2.xlsm',
                'sheet' => 'Estrategia',
                'rows' => 366,
            ],
        ]);
    }

    public function annual(?int $year = null): void
    {
        $params = ['mode' => 'annual'];
        if ($year !== null) {
            $params['year'] = (string) $year;
        }
        $query = http_build_query(array_merge($params, $this->filtersFromRequest()));
        $this->response->redirect(route_url('calendar/index' . ($query !== '' ? '?' . $query : '')));
    }

    public function monthly(?int $year = null, ?int $month = null): void
    {
        $params = ['mode' => 'monthly'];
        if ($year !== null) {
            $params['year'] = (string) $year;
        }
        if ($month !== null) {
            $params['month'] = (string) $month;
        }
        $query = http_build_query(array_merge($params, $this->filtersFromRequest()));
        $this->response->redirect(route_url('calendar/index' . ($query !== '' ? '?' . $query : '')));
    }

    public function period(): void
    {
        $params = [
            'mode' => 'period',
            'start_date' => (string) $this->request->get('start_date', date('Y-m-01')),
            'end_date' => (string) $this->request->get('end_date', date('Y-m-t')),
        ];
        $query = http_build_query(array_merge($params, $this->filtersFromRequest()));
        $this->response->redirect(route_url('calendar/index' . ($query !== '' ? '?' . $query : '')));
    }

    public function saveNote(): void
    {
        $this->boot('client.calendar');
        $this->ensurePostWithCsrf();

        $noteDate = (string) $this->request->post('note_date');
        $contextType = (string) $this->request->post('context_type', 'editorial');
        $noteText = trim((string) $this->request->post('note_text'));

        if ($noteDate === '' || $noteText === '') {
            flash('error', $this->t('calendar.flash_note_required', 'Data e observação são obrigatórias.'));
            $this->response->redirect(route_url('calendar/index?' . $this->buildReturnQueryFromPost()));
        }

        $this->loader->model('planner')->upsertDayNote(
            (int) ($this->auth->user()['id'] ?? 0),
            $noteDate,
            $contextType,
            $noteText
        );

        flash('success', $this->t(
            'calendar.flash_note_saved',
            'Observação salva para {date}.',
            ['date' => $noteDate]
        ));
        $this->response->redirect(route_url('calendar/index?' . $this->buildReturnQueryFromPost()));
    }

    public function saveExtraEvent(): void
    {
        $this->boot('client.calendar');
        $this->ensurePostWithCsrf();

        $eventDate = (string) $this->request->post('event_date');
        $title = trim((string) $this->request->post('title'));
        $eventType = trim((string) $this->request->post('event_type', 'extra'));
        $description = trim((string) $this->request->post('description'));
        $colorHex = trim((string) $this->request->post('color_hex', ''));

        if ($eventDate === '' || $title === '') {
            flash('error', $this->t('calendar.flash_extra_event_required', 'Informe data e título para o evento extra.'));
            $this->response->redirect(route_url('calendar/index?' . $this->buildReturnQueryFromPost()));
        }

        $this->loader->model('planner')->createExtraEvent((int) ($this->auth->user()['id'] ?? 0), [
            'event_date' => $eventDate,
            'title' => $title,
            'event_type' => $eventType,
            'description' => $description,
            'color_hex' => $colorHex,
        ]);

        flash('success', $this->t('calendar.flash_extra_event_created', 'Evento extra cadastrado.'));
        $this->response->redirect(route_url('calendar/index?' . $this->buildReturnQueryFromPost()));
    }

    public function saveColors(): void
    {
        $this->boot('client.calendar');
        $this->ensurePostWithCsrf();

        $colors = [
            'holiday_national' => (string) $this->request->post('holiday_national', ''),
            'holiday_international' => (string) $this->request->post('holiday_international', ''),
            'holiday_regional' => (string) $this->request->post('holiday_regional', ''),
            'commemorative' => (string) $this->request->post('commemorative', ''),
            'suggestion' => (string) $this->request->post('suggestion', ''),
            'campaign' => (string) $this->request->post('campaign', ''),
            'base_event' => (string) $this->request->post('base_event', ''),
            'extra_event' => (string) $this->request->post('extra_event', ''),
            'note' => (string) $this->request->post('note', ''),
        ];

        $this->loader->model('planner')->saveCalendarColors((int) ($this->auth->user()['id'] ?? 0), $colors);
        flash('success', $this->t('calendar.flash_colors_saved', 'Cores personalizadas salvas.'));
        $this->response->redirect(route_url('calendar/index?' . $this->buildReturnQueryFromPost()));
    }

    public function deleteExtraEvent(int $eventId): void
    {
        $this->boot('client.calendar');
        $this->ensurePostWithCsrf();
        $this->loader->model('planner')->deleteExtraEvent((int) ($this->auth->user()['id'] ?? 0), $eventId);

        flash('success', $this->t('calendar.flash_extra_event_deleted', 'Evento extra removido.'));
        $return = $this->buildReturnQueryFromPost();
        $this->response->redirect(route_url('calendar/index?' . $return));
    }

    private function filtersFromRequest(): array
    {
        return [
            'channel_id' => $this->request->get('channel_id', ''),
            'objective_id' => $this->request->get('objective_id', ''),
            'campaign_id' => $this->request->get('campaign_id', ''),
            'show_holiday_national' => (int) $this->request->get('show_holiday_national', 1),
            'show_holiday_regional' => (int) $this->request->get('show_holiday_regional', 1),
            'show_holiday_international' => (int) $this->request->get('show_holiday_international', 1),
            'show_commemoratives' => (int) $this->request->get('show_commemoratives', 1),
            'show_suggestions' => (int) $this->request->get('show_suggestions', 1),
            'show_base_events' => (int) $this->request->get('show_base_events', 1),
        ];
    }

    private function mergeManualLayers(array &$events, array $notes, array $extraEvents, string $startDate, string $endDate): void
    {
        foreach ($notes as $date => $rows) {
            if ($date < $startDate || $date > $endDate) {
                continue;
            }
            $events[$date]['notes'] = $rows;
        }

        foreach ($extraEvents as $date => $rows) {
            if ($date < $startDate || $date > $endDate) {
                continue;
            }
            $events[$date]['extra_events'] = $rows;
        }
    }

    private function buildReturnQuery(array $base = []): string
    {
        $params = array_merge($base, $this->filtersFromRequest());
        $clean = [];

        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $clean[$key] = $value;
        }

        return http_build_query($clean);
    }

    private function buildReturnQueryFromPost(): string
    {
        $base = [
            'mode' => (string) $this->request->post('return_mode', 'monthly'),
            'year' => (string) $this->request->post('return_year', date('Y')),
            'month' => (string) $this->request->post('return_month', date('n')),
            'start_date' => (string) $this->request->post('return_start_date', date('Y-m-01')),
            'end_date' => (string) $this->request->post('return_end_date', date('Y-m-t')),
            'channel_id' => (string) $this->request->post('return_channel_id', ''),
            'objective_id' => (string) $this->request->post('return_objective_id', ''),
            'campaign_id' => (string) $this->request->post('return_campaign_id', ''),
            'show_holiday_national' => (string) $this->request->post('return_show_holiday_national', '1'),
            'show_holiday_regional' => (string) $this->request->post('return_show_holiday_regional', '1'),
            'show_holiday_international' => (string) $this->request->post('return_show_holiday_international', '1'),
            'show_commemoratives' => (string) $this->request->post('return_show_commemoratives', '1'),
            'show_suggestions' => (string) $this->request->post('return_show_suggestions', '1'),
            'show_base_events' => (string) $this->request->post('return_show_base_events', '1'),
        ];

        return http_build_query($base);
    }
}
