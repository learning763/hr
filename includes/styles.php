<style>
    @font-face {
        font-family: 'Kalimati';
        src: url('fonts/kalimati.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
        font-display: swap;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Kalimati', sans-serif; background: #f4f6f9; color: #333; min-height: 100vh; display: flex; flex-direction: column; }
    a { text-decoration: none; color: inherit; }
    .layout { display: flex; flex: 1; }
    .main-content { flex: 1; display: flex; flex-direction: column; overflow-x: hidden; }
    .page-content { flex: 1; padding: 20px 24px; }
    .page-title { font-size: 19px; font-weight: 700; color: #10263f; margin-bottom: 3px; }
    .page-subtitle { font-size: 12px; color: #8a99b0; margin-bottom: 18px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat-card { background: #fff; border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border: 1px solid #eef2f6; }
    .stat-icon { width: 38px; height: 38px; border-radius: 10px; background: #eef2f6; display: flex; align-items: center; justify-content: center; color: #0e7490; font-size: 16px; }
    .stat-value { font-size: 19px; font-weight: 700; color: #10263f; }
    .stat-label { font-size: 11px; color: #8a99b0; }
    .data-table { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border: 1px solid #eef2f6; }
    .data-table table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 10px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #8a99b0; background: #fafbfc; border-bottom: 1px solid #eef2f6; }
    .data-table td { padding: 10px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f0f2f5; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: #fafbfc; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #d1fae5; color: #065f46; }
    .badge.leave { background: #fef3c7; color: #92400e; }
    .profile-card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border: 1px solid #eef2f6; }
    .info-row { display: flex; padding: 11px 0; border-bottom: 1px solid #f0f2f5; }
    .info-row:last-child { border-bottom: none; }
    .info-label { width: 150px; font-size: 13px; color: #8a99b0; font-weight: 500; }
    .info-value { font-size: 13px; color: #333; font-weight: 500; }
    .sidebar-link { display: flex; align-items: center; padding: 10px 20px; color: #5b6e8c; font-size: 13px; font-weight: 500; border-left: 3px solid transparent; transition: all 0.2s; }
    .sidebar-link i { margin-right: 10px; width: 18px; text-align: center; }
    .sidebar-link:hover { background: #e6f4fa; color: #10263f; }
    .sidebar-link.active { color: #10263f; border-left-color: #0e7490; background: #e6f4fa; }
    @media (max-width: 768px) { .layout { flex-direction: column; } .page-content { padding: 16px 14px; } .stats-grid { grid-template-columns: 1fr 1fr; } }
</style>
