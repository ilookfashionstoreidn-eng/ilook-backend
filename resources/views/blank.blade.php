<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blank Dashboard</title>
    <style>
        :root {
            --bg: #e9e9e9;
            --surface: #f5f5f5;
            --panel: #ffffff;
            --text: #1e1e1e;
            --muted: #7f7f84;
            --line: #e2e2e4;
            --accent: #5b35d6;
            --accent-soft: #efebff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .page {
            min-height: 100vh;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .app-shell {
            width: min(1320px, 100%);
            min-height: 86vh;
            background: var(--surface);
            border-radius: 14px;
            border: 1px solid #dddddf;
            overflow: hidden;
            display: grid;
            grid-template-columns: 260px 1fr;
        }

        .sidebar {
            background: #f3f3f4;
            border-right: 1px solid var(--line);
            padding: 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .brand {
            height: 56px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 34px;
            font-weight: 700;
            padding: 0 6px;
        }

        .brand .dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 5px rgba(91, 53, 214, 0.12);
        }

        .menu-group {
            margin-top: 8px;
        }

        .menu-title {
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 10px 8px;
        }

        .menu-item {
            height: 42px;
            border-radius: 10px;
            padding: 0 12px;
            display: flex;
            align-items: center;
            color: #5d5d63;
            margin-bottom: 6px;
        }

        .menu-item.active {
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 600;
        }

        .sidebar-spacer {
            flex: 1;
        }

        .content {
            display: flex;
            flex-direction: column;
        }

        .topbar {
            height: 84px;
            padding: 0 24px;
            border-bottom: 1px solid var(--line);
            background: #f6f6f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar h1 {
            margin: 0;
            font-size: 40px;
            font-weight: 700;
            line-height: 1;
        }

        .topbar .status {
            margin-left: 16px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            color: #606066;
            font-size: 13px;
            background: #fff;
            cursor: pointer;
        }

        .top-left {
            display: flex;
            align-items: center;
        }

        .top-right {
            display: flex;
            align-items: center;
            gap: 14px;
            color: #5a5a61;
            font-size: 14px;
        }

        .top-action {
            border: 1px solid var(--line);
            background: #fff;
            color: #5a5a61;
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(145deg, #dbdbdf, #bdbdc4);
            border: 1px solid #b5b5bc;
        }

        .main {
            padding: 22px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            background: #f0f0f1;
            flex: 1;
        }

        .card {
            border-radius: 12px;
            background: var(--panel);
            border: 1px solid #e6e6e8;
            min-height: 135px;
        }

        .card.large {
            grid-column: span 2;
            min-height: 240px;
        }

        .card.tall {
            min-height: 240px;
        }

        .card.full {
            grid-column: span 3;
            min-height: 220px;
        }

        .blank-label {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a0a0a8;
            font-size: 18px;
            letter-spacing: 0.02em;
        }

        .status-wrap {
            position: relative;
        }

        .status-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 16px;
            width: 150px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 6px;
            display: none;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
            z-index: 5;
        }

        .status-menu.open {
            display: block;
        }

        .status-option {
            width: 100%;
            text-align: left;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #46464b;
            padding: 8px 10px;
            cursor: pointer;
            font-size: 13px;
        }

        .status-option:hover {
            background: #f4f4f6;
        }

        @media (max-width: 1100px) {
            .app-shell {
                grid-template-columns: 90px 1fr;
            }

            .brand span,
            .menu-title,
            .menu-item span {
                display: none;
            }

            .menu-item {
                justify-content: center;
            }
        }

        @media (max-width: 840px) {
            .app-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .topbar h1 {
                font-size: 30px;
            }

            .main {
                grid-template-columns: 1fr;
            }

            .card.large,
            .card.full {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="page">
         <div class="app-shell">
            <aside class="sidebar">
                <div class="brand">
                    <div class="dot"></div>
                    <span>Drivergo</span>
                </div>

                <div class="menu-group">
                    <div class="menu-title">Main Menu</div>
                    <div class="menu-item"><span>Overview</span></div>
                    <div class="menu-item active"><span>Shipment</span></div>
                    <div class="menu-item"><span>Orders</span></div>
                    <div class="menu-item"><span>Message</span></div>
                    <div class="menu-item"><span>Activity</span></div>
                </div>

                <div class="menu-group">
                    <div class="menu-title">General</div>
                    <div class="menu-item"><span>Report</span></div>
                    <div class="menu-item"><span>Support</span></div>
                    <div class="menu-item"><span>Account</span></div>
                </div>

                <div class="sidebar-spacer"></div>

                <div class="menu-group">
                    <div class="menu-title">Others</div>
                    <div class="menu-item"><span>Settings</span></div>
                    <div class="menu-item"><span>Log Out</span></div>
                </div>
            </aside>
 
            <section class="content">
                <header class="topbar">
                    <div class="top-left">
                        <h1>Shipment Track</h1>
                        <div class="status-wrap">
                            <button class="status" id="statusButton" type="button">Status</button>
                            <div class="status-menu" id="statusMenu">
                                <button class="status-option" type="button" data-status="In Transit">In Transit</button>
                                <button class="status-option" type="button" data-status="Delivered">Delivered</button>
                                <button class="status-option" type="button" data-status="Pending">Pending</button>
                            </div>
                        </div>
                    </div>
                    <div class="top-right">
                        <button class="top-action" id="searchAction" type="button">Search</button>
                        <button class="top-action" id="alertAction" type="button">Alert</button>
                        <div class="avatar"></div>
                    </div>
                </header>

                <main class="main">
                    <div class="card"></div>
                    <div class="card"></div>
                    <div class="card"></div>
                    <div class="card"></div>
                    <div class="card"></div>
                    <div class="card tall"></div>
                    <div class="card large"></div>
                    <div class="card"></div>
                    <div class="card full">
                        <div class="blank-label" id="blankLabel">Blank Content Area</div>
                    </div>
                </main>
            </section>
        </div>
    </div>
    <script src="{{ asset('js/blank.js') }}"></script>
</body>
</html>
