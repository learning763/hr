<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; color: #333; min-height: 100vh; display: flex; flex-direction: column; }
    a { text-decoration: none; color: inherit; }
    .layout { display: flex; flex: 1; }
    .main-content { flex: 1; display: flex; flex-direction: column; overflow-x: hidden; }
    .page-content { flex: 1; padding: 28px 32px; }
    .page-title { font-size: 22px; font-weight: 700; color: #1e3a32; margin-bottom: 4px; }
    .page-subtitle { font-size: 13px; color: #8a99b0; margin-bottom: 24px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
    .stat-card { background: #fff; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border: 1px solid #eef2f6; }
    .stat-icon { width: 44px; height: 44px; border-radius: 10px; background: #eef2f6; display: flex; align-items: center; justify-content: center; color: #2c5f4e; font-size: 18px; }
    .stat-value { font-size: 22px; font-weight: 700; color: #1e3a32; }
    .stat-label { font-size: 12px; color: #8a99b0; }
    .data-table { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border: 1px solid #eef2f6; }
    .data-table table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 14px 20px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #8a99b0; background: #fafbfc; border-bottom: 1px solid #eef2f6; }
    .data-table td { padding: 14px 20px; font-size: 13px; color: #444; border-bottom: 1px solid #f0f2f5; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: #fafbfc; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #d1fae5; color: #065f46; }
    .badge.leave { background: #fef3c7; color: #92400e; }
    .profile-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border: 1px solid #eef2f6; }
    .info-row { display: flex; padding: 14px 0; border-bottom: 1px solid #f0f2f5; }
    .info-row:last-child { border-bottom: none; }
    .info-label { width: 160px; font-size: 13px; color: #8a99b0; font-weight: 500; }
    .info-value { font-size: 13px; color: #333; font-weight: 500; }
    .sidebar-link { display: flex; align-items: center; padding: 12px 24px; color: #5b6e8c; font-size: 14px; font-weight: 500; border-left: 3px solid transparent; transition: all 0.2s; }
    .sidebar-link i { margin-right: 12px; width: 20px; text-align: center; }
    .sidebar-link:hover { background: #f0f7f4; color: #1e3a32; }
    .sidebar-link.active { color: #1e3a32; border-left-color: #2c5f4e; background: #f0f7f4; }
    @media (max-width: 768px) { .layout { flex-direction: column; } .page-content { padding: 20px 16px; } .stats-grid { grid-template-columns: 1fr 1fr; } }
</style>
