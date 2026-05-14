<?php
// Simple KPI module: show counts for projects, suppliers, employees, materials
$projects = sb_get_table('projects'); $projectsCount = is_array($projects['body'])?count($projects['body']):0;
$employees = sb_get_table('employees'); $employeesCount = is_array($employees['body'])?count($employees['body']):0;
$materials = sb_get_table('materials'); $materialsCount = is_array($materials['body'])?count($materials['body']):0;
$suppliers = sb_get_table('profiles','role=eq.supplier&select=id'); $suppliersCount = is_array($suppliers['body'])?count($suppliers['body']):0;
?>
<div class="card" style="display:flex;gap:12px">
    <div style="flex:1;padding:12px">
        <h4>Projects</h4>
        <div style="font-size:24px;font-weight:700"><?php echo $projectsCount; ?></div>
    </div>
    <div style="flex:1;padding:12px">
        <h4>Employees</h4>
        <div style="font-size:24px;font-weight:700"><?php echo $employeesCount; ?></div>
    </div>
    <div style="flex:1;padding:12px">
        <h4>Materials</h4>
        <div style="font-size:24px;font-weight:700"><?php echo $materialsCount; ?></div>
    </div>
    <div style="flex:1;padding:12px">
        <h4>Suppliers</h4>
        <div style="font-size:24px;font-weight:700"><?php echo $suppliersCount; ?></div>
    </div>
</div>
