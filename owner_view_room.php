<?php
session_start();
require_once __DIR__ . '/config.php';

$roomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($roomId <= 0) { header("Location: index.php"); exit; }

/* Fetch room + owner */
$sql = "SELECT r.*, u.name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
        FROM rooms r
        JOIN users u ON u.id = r.owner_id
        WHERE r.id = ? LIMIT 1";
$st = $conn->prepare($sql);
$st->bind_param("i", $roomId);
$st->execute();
$room = $st->get_result()->fetch_assoc();
$st->close();
if (!$room) { header("Location: index.php"); exit; }

/* Images (cover first) */
$imgs = [];
$gi = $conn->prepare("SELECT file_name, is_primary FROM room_images WHERE room_id=? ORDER BY is_primary DESC, id ASC");
$gi->bind_param("i", $roomId);
$gi->execute();
$imgs = $gi->get_result()->fetch_all(MYSQLI_ASSOC);
$gi->close();

/* Function to show room status badges */
function badgeHtml($status){
    if ($status === 'available') return '<span class="badge avail">Available</span>';
    if ($status === 'few_left') return '<span class="badge few">Few Left</span>';
    return '<span class="badge unavail">Unavailable</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($room['title']); ?> - Room Finder</title>
    <style>
        :root {
            --primary: #2D89EF;
            --primary-hover: #1E5BB8;
            --bg: #F8F9FA;
            --text: #212529;
            --muted: #6C757D;
            --border: #DEE2E6;
            --card: #FFFFFF;
            --footer-bg: #343A40;
            --success: #28A745;
            --warning: #FFC107;
            --danger: #DC3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        main {
            flex: 1;
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 22px auto;
        }

        /* Navbar */
        .nav {
            background: #fff;
            border-bottom: 1px solid var(--border);
        }

        .nav__inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
        }

        .brand {
            font-size: 20px;
            font-weight: 800;
        }

        .brand span {
            color: var(--primary);
        }

        .nav__links a {
            margin-left: 18px;
            color: var(--muted);
        }

        .nav__links a:hover {
            color: var(--primary);
        }

        /* Title */
        .top {
            margin: 14px 0;
        }

        .title {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.2;
        }

        .subtitle {
            color: var(--muted);
            margin-top: 6px;
        }

        /* Layout (static sidebar) */
        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 22px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .card-body {
            padding: 16px;
        }

        /* Main column */
        .cover {
            width: 100%;
            height: 440px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid var(--border);
        }

        @media (max-width: 992px) {
            .cover {
                height: 300px;
            }
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 18px;
        }

        .chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .chip {
            padding: 6px 10px;
            border-radius: 999px;
            background: #EEF2FF;
            color: #27336A;
            font-size: 12px;
            border: 1px solid #E0E7FF;
        }

        .meta {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Sidebar (STATIC) */
        .aside {
            width: 100%;
        }

        .facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .fact {
            background: #F9FAFB;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            font-size: 13px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--primary);
            color: #fff;
            background: var(--primary);
            font-weight: 600;
        }

        .btn:hover {
            background: var(--primary-hover);
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge.avail {
            background: #E8F8EE;
            color: #167C36;
        }

        .badge.few {
            background: #FFF5E6;
            color: #8A5A08;
        }

        .badge.unavail {
            background: #F1F5F9;
            color: #374151;
        }

        /* Footer */
        .footer {
            background: var(--footer-bg);
            color: #e5e7eb;
            padding: 22px 0;
            text-align: center;
            margin-top: 26px;
        }

        footer p {
            color: #fff;
        }
    </style>
</head>
<body>
    <header class="nav">
        <div class="container nav__inner">
            <a class="brand" href="index.php"><span>Room</span>Finder</a>
            <nav class="nav__links">
                <a href="search.php">Search</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <!-- Title block (no status badge here) -->
            <div class="top">
                <div class="title"><?php echo htmlspecialchars($room['title']); ?></div>
                <div class="subtitle">
                    <?php echo ucfirst($room['type']); ?>
                    • Rs. <?php echo number_format($room['price'],0); ?> / month
                    • Cap: <?php echo (int)$room['capacity']; ?>
                    • <?php echo htmlspecialchars(trim(($room['area']?:'').' '.($room['city']?:''))); ?>
                </div>
            </div>

            <div class="layout">
                <!-- MAIN -->
                <section>
                    <div class="card">
                        <img class="cover" src="<?php echo $imgs ? 'uploads/rooms/'.htmlspecialchars($imgs[0]['file_name']) : 'images/placeholder.jpg'; ?>" alt="Cover">
                    </div>

                    <div class="card" style="margin-top:16px">
                        <div class="card-body">
                            <div class="section-title">Description</div>
                            <p class="meta" style="color:#111;margin-top:6px">
                                <?php echo nl2br(htmlspecialchars($room['description'] ?: 'No description provided.')); ?>
                            </p>

                            <?php
                                $amen = array_filter(explode(',', (string)$room['amenities']));
                                if ($amen):
                            ?>
                            <div class="chips">
                                <?php foreach($amen as $a): ?>
                                <span class="chip"><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$a))); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- STATIC SIDEBAR -->
                <aside class="aside">
                    <div class="card">
                        <div class="card-body">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                <div class="section-title" style="margin:0">Location & Owner</div>
                                <!-- single status badge lives here -->
                                <?php echo badgeHtml($room['status']); ?>
                            </div>

                            <div class="facts">
                                <div class="fact">Type<br><strong><?php echo ucfirst($room['type']); ?></strong></div>
                                <div class="fact">Capacity<br><strong><?php echo (int)$room['capacity']; ?></strong></div>
                                <div class="fact">Price<br><strong>Rs. <?php echo number_format($room['price'],0); ?></strong></div>
                                <div class="fact">City<br><strong><?php echo htmlspecialchars($room['city'] ?: '-'); ?></strong></div>
                            </div>

                            <div style="margin-top:12px" class="meta">
                                <?php echo htmlspecialchars($room['address'] ?: ''); ?><br>
                                <?php echo htmlspecialchars(trim(($room['area']?:'').' '.($room['city']?:''))); ?>
                            </div>

                            <div style="margin-top:12px">
                                <div class="meta"><strong>Owner:</strong> <?php echo htmlspecialchars($room['owner_name']); ?></div>
                                <?php if(isset($_SESSION['user']) && $_SESSION['user']['role']==='owner' && $_SESSION['user']['id']==$room['owner_id']): ?>
                                    <a class="btn" href="edit_room.php?id=<?php echo (int)$roomId; ?>" style="margin-top:10px;display:inline-block">Edit this room</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            Room Finder helps students and owners connect easily. | Email: info@roomfinder.com
        </div>
    </footer>
</body>
</html>
