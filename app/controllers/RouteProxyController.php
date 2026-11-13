<?php
declare(strict_types=1);

final class RouteProxyController
{
    public static function route(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            if (current_user() === null) {
                self::respond(['error' => 'Your session expired. Sign in again.'], 401);
                return;
            }
            require_post();
            verify_csrf();
            $raw = file_get_contents('php://input');
            if ($raw === false || strlen($raw) > 4096) {
                throw new InvalidArgumentException('Invalid route request.');
            }
            $request = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $ids = $request['branch_ids'] ?? null;
            if (!is_array($ids) || count($ids) < 2 || count($ids) > 12 || array_values($ids) !== $ids) {
                throw new InvalidArgumentException('Choose an origin, up to 10 stops, and a destination.');
            }
            $validatedIds = [];
            foreach ($ids as $id) {
                if (!is_int($id) || $id < 1 || in_array($id, $validatedIds, true)) {
                    throw new InvalidArgumentException('Route branches must be unique active locations.');
                }
                $validatedIds[] = $id;
            }
            $branches = Branch::activeByIds($validatedIds);
            if (count($branches) !== count($validatedIds)) {
                throw new InvalidArgumentException('One or more route branches are inactive or missing.');
            }
            $coordinates = [];
            foreach ($validatedIds as $id) {
                $branch = $branches[$id];
                $coordinates[] = [(float) $branch['lng'], (float) $branch['lat']];
            }
            self::respond(RouteService::route($coordinates));
        } catch (RoutingException $error) {
            self::respond(['error' => $error->getMessage()], $error->httpStatus);
        } catch (InvalidArgumentException | JsonException $error) {
            self::respond(['error' => $error->getMessage()], 422);
        } catch (RuntimeException $error) {
            $status = http_response_code();
            self::respond(['error' => $error->getMessage()], $status >= 400 ? $status : 400);
        } catch (Throwable $error) {
            error_log((string) $error);
            self::respond(['error' => 'Could not load the route. Try again or contact an admin.'], 500);
        }
    }

    private static function respond(array $body, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_THROW_ON_ERROR);
    }
}
