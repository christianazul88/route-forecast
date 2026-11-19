<?php
declare(strict_types=1);

final class LoadPlanController
{
    public static function index(): void
    {
        require_login();
        $trucks = Truck::active();
        $boxes = BoxType::active();
        $selectedTruck = filter_var($_GET['truck_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tripId = isset($_GET['trip_id']) ? positive_id($_GET['trip_id']) : null;
        $detail = $context = $saved = null;
        if ($tripId !== null) {
            $detail = Trip::findDetail($tripId);
            if (!$detail) throw new InvalidArgumentException('Trip not found.');
            $selectedTruck = (int) $detail['trip']['truck_id'];
            $truck = Truck::find($selectedTruck);
            $context = LoadPlan::context($detail, $truck);
            if (!in_array($selectedTruck, array_map('intval', array_column($trucks, 'id')), true)) $trucks[] = $truck;
            $saved = LoadPlan::find($tripId);
        }
        $demoStops = [];
        foreach (Branch::active() as $branch) {
            if (in_array($branch['code'], ['DEMO-BOC', 'DEMO-VAL'], true)) {
                $demoStops[$branch['code']] = $branch['name'];
            }
        }
        $trips = db()->query("SELECT id, trip_no, trip_date FROM trips WHERE status = 'Planned'
            ORDER BY trip_date DESC, id DESC LIMIT 30")->fetchAll();
        render('load_planner/index', compact('trucks', 'boxes', 'selectedTruck', 'detail', 'context', 'saved', 'trips', 'demoStops') + [
            'title' => 'Load planner',
        ]);
    }

    public static function save(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $user = current_user();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Your session expired. Sign in again.']);
                return;
            }
            require_post();
            verify_csrf();
            $input = file_get_contents('php://input', false, null, 0, 500001);
            if ($input === false || strlen($input) > 500000) throw new InvalidArgumentException('The load plan is too large.');
            $payload = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || !is_int($payload['tripId'] ?? null) || $payload['tripId'] < 1) {
                throw new InvalidArgumentException('Choose a planned trip.');
            }
            $revision = LoadPlan::save($payload['tripId'], $payload, (int) $user['id']);
            echo json_encode(['revision' => $revision], JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException | JsonException $error) {
            http_response_code(422);
            echo json_encode(['error' => $error->getMessage()]);
        } catch (Throwable $error) {
            $status = http_response_code();
            http_response_code($status >= 400 ? $status : 500);
            if ($status < 400) error_log((string) $error);
            echo json_encode(['error' => $status >= 400 ? $error->getMessage() : 'Could not save the load plan.']);
        }
    }
}
