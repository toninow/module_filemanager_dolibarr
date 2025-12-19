<?php
/**
 * Página de estadísticas de uso de Dolibarr
 */

// Cargar entorno de Dolibarr
require_once '../../main.inc.php';
require_once 'lib/filemanager.lib.php';
require_once 'lib/usage_tracker.lib.php';

// Verificar permisos de administrador
if (empty($user->admin)) {
    accessforbidden('Solo administradores');
}

llxHeader('', 'Estadísticas de Uso', '', '', 0, 0, '', '', '');

$token = newToken();

// Obtener estadísticas
$peak_hours = getPeakHours(7);
$usage_stats = getUsageStats(7);
$user_stats = getUserUsageStats(null, 30);

// Obtener horas de mayor uso para el gráfico
$hourly_data = array();
for ($i = 0; $i < 24; $i++) {
    $hourly_data[$i] = 0;
}

foreach ($peak_hours as $peak) {
    $hourly_data[$peak['hour']] = $peak['total_sessions'];
}

// Obtener estadísticas por día
$daily_data = array();
foreach ($usage_stats as $stat) {
    $daily_data[] = array(
        'date' => $stat['date'],
        'sessions' => $stat['sessions'],
        'unique_users' => $stat['unique_users']
    );
}

print '<div class="setup-page">';
print '<h1>📊 Estadísticas de Uso de Dolibarr</h1>';

// Pestañas
print '<div style="margin-bottom: 20px;">';
print '<div style="display: flex; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden;">';
print '<button class="setup-tab active" onclick="window.location.href=\'stats.php\'" style="padding: 10px 20px; border: none; background: #007bff; color: white; cursor: pointer;"><i class="fas fa-chart-line"></i> Estadísticas</button>';
print '<button class="setup-tab" onclick="window.location.href=\'setup.php\'" style="padding: 10px 20px; border: none; background: #f8f9fa; color: #495057; cursor: pointer;"><i class="fas fa-cog"></i> Configuración</button>';
print '<button class="setup-tab" onclick="window.location.href=\'setup.php?tab=backup\'" style="padding: 10px 20px; border: none; background: #f8f9fa; color: #495057; cursor: pointer;"><i class="fas fa-server"></i> Backups</button>';
print '</div>';
print '</div>';

// Estadísticas de horas pico
print '<div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px;">';
print '<h3 style="margin: 0 0 15px 0;">⏰ Horas de Mayor Uso (Últimos 7 días)</h3>';

print '<canvas id="peakHoursChart" style="max-height: 300px;"></canvas>';

print '<div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';

$top_hours = array_slice($peak_hours, 0, 6);
foreach ($top_hours as $peak) {
    $percentage = count($peak_hours) > 0 ? round(($peak['total_sessions'] / array_sum(array_column($peak_hours, 'total_sessions'))) * 100, 1) : 0;
    
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #007bff;">';
    print '<div style="font-size: 24px; font-weight: bold; color: #007bff;">' . str_pad($peak['hour'], 2, '0', STR_PAD_LEFT) . ':00</div>';
    print '<div style="color: #6c757d; font-size: 13px;">' . $peak['total_sessions'] . ' sesiones</div>';
    print '<div style="color: #6c757d; font-size: 12px;">' . $percentage . '% del uso diario</div>';
    print '</div>';
}

print '</div>';
print '</div>';

// Mejor hora para backup
if (!empty($peak_hours)) {
    $min_activity_hour = null;
    $min_activity = PHP_INT_MAX;
    
    for ($h = 0; $h < 24; $h++) {
        $found = false;
        $total = 0;
        
        foreach ($peak_hours as $peak) {
            if ($peak['hour'] == $h) {
                $total = $peak['total_sessions'];
                $found = true;
                break;
            }
        }
        
        if (!$found || $total < $min_activity) {
            $min_activity = $total;
            $min_activity_hour = $h;
        }
    }
    
    if ($min_activity_hour !== null) {
        print '<div style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 8px; padding: 20px; margin-bottom: 20px;">';
        print '<p style="margin: 0; font-size: 16px; color: #004085;">La hora con menor actividad es: <strong>' . str_pad($min_activity_hour, 2, '0', STR_PAD_LEFT) . ':00</strong></p>';
        print '<p style="margin: 5px 0 0 0; color: #6c757d; font-size: 14px;">En ese momento hay solo ' . $min_activity . ' sesiones activas en promedio</p>';
        print '</div>';
    }
}

// Tabla de usuarios más activos
print '<div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px;">';
print '<h3 style="margin: 0 0 15px 0;">👥 Usuarios Más Activos (Últimos 30 días)</h3>';

print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<th>Usuario</th>';
print '<th class="right">Sesiones</th>';
print '<th class="right">Acciones</th>';
print '<th class="right">Tiempo Promedio</th>';
print '<th>Última Actividad</th>';
print '</tr>';

$var = true;
foreach ($user_stats as $stat) {
    print '<tr class="' . ($var ? 'oddeven' : 'even') . '">';
    print '<td><strong>' . htmlspecialchars($stat['user_name']) . '</strong></td>';
    print '<td class="right">' . number_format($stat['total_sessions']) . '</td>';
    print '<td class="right">' . number_format($stat['total_actions']) . '</td>';
    print '<td class="right">' . number_format($stat['avg_session_minutes'], 1) . ' min</td>';
    print '<td>' . dol_print_date(strtotime($stat['last_session']), 'dayhourshort') . '</td>';
    print '</tr>';
    $var = !$var;
}

print '</table>';
print '</div>';


print '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
print '<script>
const ctx = document.getElementById("peakHoursChart").getContext("2d");
const peakHoursChart = new Chart(ctx, {
    type: "bar",
    data: {
        labels: ' . json_encode(array_map(function($h) { return str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; }, range(0, 23))) . ',
        datasets: [{
            label: "Sesiones por Hora",
            data: ' . json_encode(array_values($hourly_data)) . ',
            backgroundColor: "rgba(0, 123, 255, 0.7)",
            borderColor: "rgba(0, 123, 255, 1)",
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>';

llxFooter();
$db->close();




