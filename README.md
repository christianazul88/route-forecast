# Route Forecast

PHP/MySQL logistics planning app for routes from the Guiguinto warehouse. Planners can find branches along a route, estimate detours, save trips, print trip sheets, and export CSV files.

The load planner estimates how many mixed packages fit in a measured truck cargo bay and how many more of each package type could be added.

## Requirements

- PHP 8.0+ with PDO MySQL, cURL, and mbstring; MySQL/MariaDB
- Apache `mod_authz_core` enabled (standard XAMPP setup)
- Internet access for Bootstrap, Leaflet, Turf, the polyline decoder, OpenStreetMap tiles, and ORS routing

## XAMPP setup

1. Place this folder at `C:\xampp\htdocs\route-forecast`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Import `database\schema.sql` in phpMyAdmin, or run:

   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'source C:/xampp/htdocs/route-forecast/database/schema.sql'
   ```

4. Copy `config\env.example` to **`C:\xampp\route-forecast.env`** (outside `htdocs`) and adjust the database credentials. The PHP app reads this file by default. You can instead set the `ROUTE_FORECAST_ENV` environment variable to another absolute path.
5. Create the first admin account from a terminal:

   ```powershell
   & 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\route-forecast\database\create_admin.php' admin 'Logistics Admin' 'use-a-long-unique-password'
   ```

6. Open [http://localhost/route-forecast/](http://localhost/route-forecast/) and sign in.

If upgrading a database created before Phase 3, run `database/migrations/003_trip_stop_legs.sql` once. New installations already include those columns in `schema.sql`.
For a database created before the load planner, run `database/migrations/004_load_planning.sql` once. Run the migration before uploading the updated PHP files to a live host. New installations already include the truck measurements and `box_types` table.

Admin users can add, edit, and archive branches and trucks. Planner users can view them. Archiving keeps references intact for future trip history; edit an archived record to reactivate it. The origin warehouse cannot be archived. The map coordinates in the seed data are approximate and should be checked with the picker before operational use.

## OpenRouteService key

Create a free account and API key at [OpenRouteService](https://openrouteservice.org/). Put the key in `C:\xampp\route-forecast.env` as `ORS_API_KEY=...`. Keep that file outside `htdocs`. The browser calls only `public/index.php?page=route_proxy`; PHP calls the [ORS Directions JSON API](https://giscience.github.io/openrouteservice/api-reference/endpoints/directions/requests-and-return-types) with the `driving-hgv` profile. The key never enters the HTML or JavaScript. If the key is missing or ORS rejects it, the forecast page shows a friendly message.

## Route Forecast behavior

- Select an active origin and main destination. The seeded Guiguinto warehouse and Valenzuela branch are preselected.
- The direct route is decoded from the ORS polyline and drawn in Leaflet. Turf measures each active branch's distance from that line. The corridor slider ranges from 0.5 to 5 km, defaults to 1.5 km, and lists matches in distance-along-route order.
- Up to 10 branches closest to the line receive detour estimates. Each estimate compares `origin → branch → destination` with the direct route. The default labels are Recommended for less than 5 extra minutes, Marginal for 5–15 minutes, and Not worth it for over 15 minutes.
- Select optional stops and press **Recalculate** to preview the ordered route through all selected stops. At most 10 optional stops are allowed.
- Requests are debounced and queued in the browser. PHP caches successful ORS responses by a SHA-256 hash of the routing profile and coordinates. The default cache lifetime is 168 hours.

Change the thresholds or cache lifetime in the `settings` table (`recommended_max_min`, `marginal_max_min`, `ors_cache_hours`). Reload the forecast page after changing thresholds. The seed branch pins are approximate; verify them before operational planning. The HGV profile uses generic truck routing because vehicle dimensions and weight restrictions have not been added to the app.

## Trips

Choose an active truck, driver, and date on Route Forecast. Save a direct route or recalculate after selecting optional stops, then save. The server reloads route geometry and distances from the ORS cache or API before storing the trip. The browser's displayed numbers are not trusted for saved records.

The dashboard shows today's trips, trips in the next seven days, and counts by status. The trips list filters by date range, status, and truck. A trip detail page draws its saved geometry without calling ORS again. Statuses follow `Planned → Dispatched → Completed`, with cancellation allowed from Planned or Dispatched. The print sheet and CSV include ordered branch addresses, contacts, per-leg distances and times when ORS provides them, optional-stop detours, and trip totals. Driving times exclude loading and unloading.

## Load planner

1. In **Trucks**, enter the measured clear interior length, width, and height in centimeters. The rear door width and height are optional but recommended. A nominal “16 ft closed van” tells you only the approximate body length (16 ft = 487.68 cm); the usable interior and door opening vary by body. Measure the actual truck before using an estimate.
2. In **Box types**, an Admin records each package's outside dimensions, weight, and whether a rectangular box may be placed on its side. Shapes are rectangular box and upright cylinder. Cylinders use a square footprint equal to their diameter, which is conservative.
3. In **Load planner**, select a saved truck or enter one-off custom truck dimensions, set wall/roof clearance, and add saved package types or one-off custom dimensions with requested quantities. Planners can enter custom trucks and package shapes without changing either catalog. The calculator places larger packages first, checks payload and optional door clearance, and shows a first-layer floor view. The **What else could fit?** table evaluates each extra package type separately on top of the planned load; its quantities are not additive.

The calculator uses an axis-aligned, row-by-row packing estimate. It does not model wheel wells, axle loads, lashing, pallet bases, package crush limits, unload order, or forklift access. It assumes stable stacking and a clear rectangular cargo bay. Confirm the physical load plan before dispatch. Plans are currently estimates in the browser; they are not saved to trips.

To verify the packing logic, run `node tests/load-packing.test.mjs` on a development machine. Node is only for tests; the app needs no build step or Node at runtime.

## Free PHP/MySQL hosting

[InfinityFree](https://www.infinityfree.com/) currently offers free PHP/MySQL hosting and a free subdomain. Its free hosting [allows same-site browser requests to a PHP API](https://forum.infinityfree.com/t/does-infinityfree-support-rest-apis/101707), and [outbound cURL is generally allowed](https://forum.infinityfree.com/t/issues-with-curl-ssl-connection-error-curl-error-56-and-opengraph-preview-failure/121232/4). ORS connectivity must still be checked on the deployed site. GitHub and Google Drive store source files; they do not run PHP/MySQL.

For a host where the app files are inside its `htdocs` web root:

1. Create a MySQL database in the hosting control panel. Note the assigned hostname, database name, username, and password.
2. Upload the application files into the site's `htdocs` folder, including the `.htaccess` files in `app`, `config`, and `database`.
3. Copy `config/env.example` to `config/.env` **on the host only**. Fill in the host's DB values, `ORS_API_KEY`, and a random `SETUP_TOKEN` of at least 20 characters. Do not commit or share `.env`. The `config/.htaccess` file must deny browser access to it; verify that `https://your-domain/config/.env` returns HTTP 403 before proceeding.
4. Select the new database in phpMyAdmin and import `database/hosting_schema.sql`. This variant omits `CREATE DATABASE` and `USE`, which free hosts commonly disallow. Regenerate it after schema changes with `php database/build_hosting_schema.php`.
   For an existing hosted installation, import only `database/migrations/004_load_planning.sql` once before uploading this module. Do not reimport `hosting_schema.sql` to upgrade an existing database.
5. Visit `https://your-domain/public/setup.php` and create the first admin using the setup token. This page becomes unavailable as soon as a user exists. Remove `SETUP_TOKEN` from `.env` afterward.
6. Open the site's root URL, sign in, verify a branch map, and test a route to confirm the host can reach ORS.

The hosted `.env` is inside the host's required web root because some free hosts do not permit files outside `htdocs`; the `config` directory is protected by `.htaccess`. If your host allows a directory outside the web root, set `ROUTE_FORECAST_ENV` to an absolute external path instead. For any public Internet deployment, use HTTPS and strong unique account passwords. The GitHub repository must never include database credentials, ORS keys, or a database export with user data.

## Files

```text
app/          Controllers, models, helpers and PHP views
config/       Bootstrap and non-secret env example
database/     Schemas, seed branches, migration, CLI admin creator
public/       Web entry point and CSS/JavaScript assets
```

`app`, `config`, `database`, and `tests` contain `.htaccess` access blocks for the standard XAMPP document root. `public/.htaccess` serves `.mjs` modules with a JavaScript MIME type. Open the app through `public/index.php` (the project root redirects there).
