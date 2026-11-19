# Route Forecast

PHP/MySQL logistics planning app for routes from the Guiguinto warehouse. Planners can find branches along a route, estimate detours, save trips, print trip sheets, and export CSV files.

The load planner estimates how many mixed packages fit in a measured truck cargo bay and how many more of each package type could be added.

The fuel planner estimates trip kilometers, diesel consumption, and fuel cost, with an optional return to the warehouse.

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

6. Open [http://localhost/route-forecast/](http://localhost/route-forecast/) and sign in. In **Branches**, save the verified warehouse address and map pin as the origin, then add your delivery sites.

If upgrading a database created before Phase 3, run `database/migrations/003_trip_stop_legs.sql` once. New installations already include those columns in `schema.sql`.
For a database created before the load planner, run `database/migrations/004_load_planning.sql` once. Run the migration before uploading the updated PHP files to a live host. New installations already include the truck measurements and `box_types` table.
For a database created before truck body types were added, run `database/migrations/005_truck_body_types.sql` once after migration 004. Existing trucks keep a blank body type until an Admin edits them; new trucks require a choice.
For a database created before irregular packages were added, apply `database/migrations/006_irregular_packages.sql` before updating the app. Existing package records keep their original shape.
For a database created before trip load plans, apply `database/migrations/007_trip_load_plans.sql`. A legacy opening appears as a centered rear opening until an Admin confirms it in Trucks. Import `database/demo_data.sql` for the training examples.
For a database with the original example locations, apply `database/migrations/008_archive_sample_branches.sql` to retire the sample branches and preserve trip history.
For a database created before the fuel planner, apply `database/migrations/009_fuel_planner.sql` before updating the app.

Admin users can add, edit, and archive branches and trucks. Planner users can view them. Archiving keeps references intact for trip history. Open **Archived branches** to review a location. The origin warehouse cannot be archived.

Admins can open **Audit log** to view sign-in attempts, sign-outs, accessed pages, form submissions, and record changes. Filter by authentication, saved changes, or failed and denied activity. The app records requested load calculations and printing from their action buttons. Passwords and setup tokens are excluded from the audit log. Successful record changes include the relevant record ID.

## OpenRouteService key

Create a free account and API key at [OpenRouteService](https://openrouteservice.org/). Put the key in `C:\xampp\route-forecast.env` as `ORS_API_KEY=...`. Keep that file outside `htdocs`. The browser calls only `public/index.php?page=route_proxy`; PHP calls the [ORS Directions JSON API](https://giscience.github.io/openrouteservice/api-reference/endpoints/directions/requests-and-return-types) with the `driving-hgv` profile. The key never enters the HTML or JavaScript. If the key is missing or ORS rejects it, the forecast page shows a friendly message.

## Route Forecast behavior

- Select an active origin and main destination. Route Forecast preselects the saved warehouse when available and waits for you to choose a delivery branch.
- The direct route is decoded from the ORS polyline and drawn in Leaflet. Turf measures each active branch's distance from that line. The corridor slider ranges from 0.5 to 5 km, defaults to 1.5 km, and lists matches in distance-along-route order.
- Up to 10 branches closest to the line receive detour estimates. Each estimate compares `origin → branch → destination` with the direct route. The default labels are Recommended for less than 5 extra minutes, Marginal for 5–15 minutes, and Not worth it for over 15 minutes.
- Select optional stops and press **Recalculate** to preview the ordered route through all selected stops. At most 10 optional stops are allowed.
- Requests are debounced and queued in the browser. PHP caches successful ORS responses by a SHA-256 hash of the routing profile and coordinates. The default cache lifetime is 168 hours.

Change the thresholds or cache lifetime in the `settings` table (`recommended_max_min`, `marginal_max_min`, `ors_cache_hours`). Reload the forecast page after changing thresholds. The HGV profile uses generic truck routing because vehicle dimensions and weight restrictions have not been added to the app.

## Trips

Choose an active truck, driver, and date on Route Forecast. Save a direct route or recalculate after selecting optional stops, then save. The server reloads route geometry and distances from the ORS cache or API before storing the trip. The browser's displayed numbers are not trusted for saved records.

The dashboard shows today's trips, trips in the next seven days, and counts by status. The trips list filters by date range, status, and truck. A trip detail page draws its saved geometry without calling ORS again. Statuses follow `Planned → Dispatched → Completed`, with cancellation allowed from Planned or Dispatched. The print sheet and CSV include ordered branch addresses, contacts, per-leg distances and times when ORS provides them, optional-stop detours, and trip totals. Driving times exclude loading and unloading.

## Fuel planner

1. In **Trucks**, an Admin can save the measured diesel efficiency in km/L for each truck. Planners can adjust it for each estimate.
2. Open **Fuel estimate** on a saved trip to use its distance through the delivery stops. Choose **One way** or **Return to warehouse**. The return is a separate truck route from the final delivery to the active warehouse. Enter a known return distance when routing is unavailable. You can enter a separate return efficiency when the truck’s load changes.
3. Enter a dated diesel price in pesos per liter and the station or published source. An Admin can record a team price from **Fuel**. The latest observed team price prefills new estimates. Check the current pump price before using an older entry.
4. Calculate the estimated distance, liters and cost. On a planned or dispatched trip, you can save the estimate with the price and route distances used. The **Fuel** menu also offers a standalone estimate with manually entered distances.

Use a station quote, receipt, or the [DOE North Luzon pump price reports](https://doe.gov.ph/data-and-prices/liquid-fuels/retail-pump-prices/north-luzon-pump-prices) for the rate. The app records entered prices and calculates estimates. Actual diesel use varies with vehicle load, driving conditions, and idling.

## Load planner

1. In **Trucks**, save the plate, body type, payload, and usable cargo dimensions in feet. The body length picker offers 10, 12, 14, 16, 18 and 20 ft as starting values. Add rear, left, and right openings with clear dimensions and offsets. Face forward to identify left and right. The suggestions from body type are editable.
2. Open **Load plan** from a saved trip, choose the doors available at each stop, and add saved package types or custom items. Assign each item to a stop, enter the quantity, and choose its stacking rule. An upright cylinder or irregular item stays on the floor with the space above it clear. Cylinders use a square footprint, and wrapped items use their measured rectangular envelope.
3. Estimate the load and view the counts for each stop. Select **Item type** or **Delivery stop** to color the 3D view. Drag horizontally to rotate, or use the rotation and elevation controls. View a floor plan, filter layers and stops, or inspect an individual placement. Crew instructions show the order and door for loading and for unloading each stop.
4. Check capacity for one package and destination at a time. The estimates compare an empty truck with adding units while keeping the current loaded quantities. Save the accepted plan to a planned trip. Recalculate if the saved truck measurements or stop list changes.

The calculator tries multiple arrangements and checks a straight, unobstructed path through an available opening for each placement. It validates each saved plan against the truck, stop order, selected doors, placements and payload on the server. It models a clear rectangular cargo bay and does not calculate turning maneuvers, axle load, equipment clearance or package crush limits. Verify the physical loading plan before dispatch.

The demo fleet uses illustrative dimensions and payloads for training. Replace those assumptions with measured dimensions and the rated payload of the actual vehicle. Published examples and body variations are available from [FUSO Philippines](https://fuso.com.ph/built-ready/) and [Taska](https://taska.com.ph/dropside-truck-bodies). **Try demo load** opens a two-stop example without saving a trip or creating map locations.

Run `node tests/load-packing.test.mjs` and `node tests/stop-packing.test.mjs` to check the packing logic. The app needs no build step or Node at runtime.

## Free PHP/MySQL hosting

[InfinityFree](https://www.infinityfree.com/) currently offers free PHP/MySQL hosting and a free subdomain. Its free hosting [allows same-site browser requests to a PHP API](https://forum.infinityfree.com/t/does-infinityfree-support-rest-apis/101707), and [outbound cURL is generally allowed](https://forum.infinityfree.com/t/issues-with-curl-ssl-connection-error-curl-error-56-and-opengraph-preview-failure/121232/4). ORS connectivity must still be checked on the deployed site. GitHub and Google Drive store source files; they do not run PHP/MySQL.

For a host where the app files are inside its `htdocs` web root:

1. Create a MySQL database in the hosting control panel. Note the assigned hostname, database name, username, and password.
2. Upload the application files into the site's `htdocs` folder, including the `.htaccess` files in `app`, `config`, and `database`.
3. Copy `config/env.example` to `config/.env` **on the host only**. Fill in the host's DB values, `ORS_API_KEY`, and a random `SETUP_TOKEN` of at least 20 characters. Do not commit or share `.env`. The `config/.htaccess` file must deny browser access to it; verify that `https://your-domain/config/.env` returns HTTP 403 before proceeding.
4. Select the new database in phpMyAdmin and import `database/hosting_schema.sql`. This variant omits `CREATE DATABASE` and `USE`, which free hosts commonly disallow. Regenerate it after schema changes with `php database/build_hosting_schema.php`.
   For an existing hosted installation, apply outstanding migrations in order from `database/migrations` before uploading the updated PHP files. Import `database/demo_data.sql` for load training. Do not reimport `hosting_schema.sql` to upgrade an existing database.
5. Visit `https://your-domain/public/setup.php` and create the first admin using the setup token. This page becomes unavailable as soon as a user exists. Remove `SETUP_TOKEN` from `.env` afterward.
6. Open the site's root URL, sign in, verify a branch map, and test a route to confirm the host can reach ORS.

The hosted `.env` is inside the host's required web root because some free hosts do not permit files outside `htdocs`; the `config` directory is protected by `.htaccess`. If your host allows a directory outside the web root, set `ROUTE_FORECAST_ENV` to an absolute external path instead. For any public Internet deployment, use HTTPS and strong unique account passwords. The GitHub repository must never include database credentials, ORS keys, or a database export with user data.

## Files

```text
app/          Controllers, models, helpers and PHP views
config/       Bootstrap and non-secret env example
database/     Schemas, load training data, migrations, CLI admin creator
public/       Web entry point and CSS/JavaScript assets
```

`app`, `config`, `database`, and `tests` contain `.htaccess` access blocks for the standard XAMPP document root. `public/.htaccess` serves `.mjs` modules with a JavaScript MIME type. Open the app through `public/index.php` (the project root redirects there).
