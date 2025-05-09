<?php
// --- Configuration & Initialization ---
ini_set('display_errors', 1); // Show errors for debugging (disable in production)
error_reporting(E_ALL);

$db_file = null;
$pdo = null;
$tables = [];
$selected_table = null;
$columns = [];
$rows = [];
$error_message = null;
$db_directory = __DIR__; // Directory where the script resides and DBs are expected

// --- Request Handling & Database Logic ---

// 1. Get Database Name from URL
if (isset($_GET['db'])) {
    $db_name_raw = basename($_GET['db']); // Basic protection against path traversal

    // Basic validation: ensure it looks like a db file and doesn't contain risky chars
    // UPDATED: Now allows .sqlite, .sqlite3, or .db extensions
    if (preg_match('/^[a-zA-Z0-9_.-]+\.(sqlite[3]?|db)$/', $db_name_raw)) { // <<< MODIFIED LINE
        $db_file_path = $db_directory . '/' . $db_name_raw;

        if (file_exists($db_file_path) && is_readable($db_file_path)) {
            $db_file = $db_name_raw; // Store the clean name for display/links
            try {
                // 2. Connect to SQLite Database
                $pdo = new PDO('sqlite:' . $db_file_path);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Throw exceptions on error

                // 3. Get List of Tables
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // 4. Get Selected Table from URL
                if (!empty($tables)) {
                    if (isset($_GET['table']) && in_array($_GET['table'], $tables)) {
                        $selected_table = $_GET['table'];
                    } else {
                        // Default to the first table if none is selected or selection is invalid
                        $selected_table = $tables[0];
                    }
                }

                // 5. Get Data and Schema for Selected Table
                if ($selected_table) {
                    try {
                        // Get Column Names using PRAGMA
                        $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($selected_table) . ")");
                        $columns_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $columns = array_column($columns_info, 'name');

                        if (!empty($columns)) {
                            // Get all rows from the selected table
                            // Quoting the table name is important if it contains special characters
                            // Note: PDO usually doesn't support placeholder for table/column names directly.
                            // We validated $selected_table against the list from sqlite_master, making this safer.
                            $stmt = $pdo->query("SELECT * FROM " . $pdo->quote($selected_table));
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                           $error_message = "Table '" . htmlspecialchars($selected_table) . "' appears to have no columns.";
                           $selected_table = null; // Reset selection if table structure is odd
                        }

                    } catch (PDOException $e) {
                        $error_message = "Error fetching data for table '" . htmlspecialchars($selected_table) . "': " . $e->getMessage();
                        $selected_table = null; // Reset selection on error
                    }
                } elseif (!empty($tables)) {
                     $error_message = "Please select a table from the sidebar.";
                } else {
                     $error_message = "No tables found in this database.";
                }

            } catch (PDOException $e) {
                $error_message = "Database Error: " . $e->getMessage();
                $db_file = $db_name_raw; // Keep name for display even if connection failed
            }
        } else {
            $error_message = "Database file not found or is not readable: " . htmlspecialchars($db_name_raw);
            $db_file = $db_name_raw; // Keep name for display
        }
    } else {
         $error_message = "Invalid database file name specified in URL. Must end with .sqlite, .sqlite3, or .db";
         if (isset($_GET['db'])) {
             $db_file = $_GET['db']; // Show the problematic name
         }
    }
} else {
    $error_message = "Please specify a database file in the URL using ?db=your_database.sqlite (or .db, .sqlite3)";
}

// Helper function to build URL parameters
function build_url($params = []) {
    // Ensure current essential params are kept unless overridden
    $current_params = $_GET;
    $merged_params = array_merge($current_params, $params);
    return basename(__FILE__) . '?' . http_build_query($merged_params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP SQLite Viewer<?php echo $db_file ? ' - ' . htmlspecialchars($db_file) : ''; ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
            background-color: #f4f5f7;
            color: #172b4d;
        }

        #sidebar {
            width: 200px;
            background-color: #ffffff;
            border-right: 1px solid #dfe1e6;
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto;
            flex-shrink: 0; /* Prevent sidebar from shrinking */
        }

        #sidebar h2 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1em;
            color: #42526e;
            border-bottom: 1px solid #dfe1e6;
            padding-bottom: 10px;
        }

        #sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        #sidebar li a {
            display: block;
            padding: 8px 10px;
            text-decoration: none;
            color: #42526e;
            border-radius: 3px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #sidebar li a:hover {
            background-color: #ebecf0;
        }

        #sidebar li a.active {
            background-color: #e6f2ff; /* Light blue background */
            color: #0052cc; /* Blue text */
            font-weight: bold;
        }

        #main-content {
            flex-grow: 1; /* Take remaining space */
            padding: 20px;
            overflow: auto; /* Add scrollbars if content overflows */
            box-sizing: border-box;
        }

        #main-content h1 {
             margin-top: 0;
             font-size: 1.5em;
             color: #091e42;
             margin-bottom: 5px;
        }
         #main-content .db-name {
             font-size: 0.9em;
             color: #5e6c84;
             margin-bottom: 20px;
             word-break: break-all; /* Prevent long db names from breaking layout */
         }


        .error-message {
            background-color: #ffebe6; /* Light red */
            color: #bf2600; /* Dark red */
            border: 1px solid #ffbdad;
            padding: 15px;
            border-radius: 3px;
            margin-bottom: 20px;
        }
         .info-message {
            background-color: #deebff; /* Light blue */
            color: #0747a6; /* Dark blue */
            border: 1px solid #a3c9ff;
            padding: 15px;
            border-radius: 3px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background-color: #fff;
            box-shadow: 0 1px 1px rgba(9,30,66,.25), 0 0 1px rgba(9,30,66,.31);
            border-radius: 3px;
            table-layout: fixed; /* Helps with column widths */
        }

        th, td {
            border: 1px solid #dfe1e6;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
            font-size: 0.9em;
            word-wrap: break-word; /* Wrap long text within cells */
        }

        th {
            background-color: #f4f5f7;
            font-weight: bold;
            color: #5e6c84;
            position: sticky; /* Make header sticky */
            top: 0; /* Stick to the top of the scrolling container */
            z-index: 1;
        }

        tbody tr:nth-child(even) {
            /* background-color: #fafbfc; */ /* Subtle striping - optional */
        }
         tbody tr:hover {
            background-color: #f0f0f0; /* Highlight row on hover */
        }

         /* Handle potential null values nicely */
         td:empty::after {
             content: "NULL";
             color: #aaa;
             font-style: italic;
         }
         td.null-value {
              color: #aaa;
              font-style: italic;
         }

    </style>
</head>
<body>

    <div id="sidebar">
        <h2>Tables</h2>
        <?php if ($db_file && $pdo): ?>
            <ul>
                <?php foreach ($tables as $table): ?>
                    <li>
                        <a href="<?php echo build_url(['table' => $table]); ?>"
                           class="<?php echo ($table === $selected_table) ? 'active' : ''; ?>"
                           title="<?php echo htmlspecialchars($table); ?>">
                            <?php echo htmlspecialchars($table); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                 <?php if (empty($tables)): ?>
                    <li><i>No tables found.</i></li>
                 <?php endif; ?>
            </ul>
        <?php elseif($db_file): ?>
             <p><i>Could not connect to database.</i></p>
        <?php else: ?>
             <p><i>No database loaded.</i></p>
        <?php endif; ?>
    </div>

    <div id="main-content">
        <?php if ($db_file): ?>
            <h1><?php echo htmlspecialchars($selected_table ?? 'Database Viewer'); ?></h1>
             <div class="db-name">Database: <strong><?php echo htmlspecialchars($db_file); ?></strong></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="<?php echo ($pdo && $selected_table === null && !empty($tables)) ? 'info-message' : 'error-message'; ?>">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($pdo && $selected_table && !empty($columns)): ?>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?php echo htmlspecialchars($column); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?php echo count($columns); ?>" style="text-align: center; font-style: italic; color: #5e6c84;">
                                Table is empty.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                     <?php
                                         $value = $row[$column];
                                         // Check explicitly for NULL as empty string or 0 might be valid data
                                         if ($value === null) {
                                             echo '<td class="null-value">NULL</td>';
                                         } else {
                                             // Escape any HTML special characters in the data
                                             echo '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'; // Added ENT_QUOTES for better security
                                         }
                                     ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php elseif($pdo && $selected_table && empty($columns) && !$error_message): ?>
             <div class="info-message">Table '<?php echo htmlspecialchars($selected_table); ?>' exists but has no columns defined.</div>
        <?php endif; ?>

    </div>

</body>
</html>