<?php
require_once 'db_config.php';

$employee_id       = (int)$_POST['employee_id'];
$basic_salary      = (float)($_POST['basic_salary']      ?? 0);
$housing_allowance = (float)($_POST['housing_allowance'] ?? 0);
$transport_allowance = (float)($_POST['transport_allowance'] ?? 0);
$medical_allowance = (float)($_POST['medical_allowance'] ?? 0);
$other_allowances  = (float)($_POST['other_allowances']  ?? 0);
$incentives_bonuses = (float)($_POST['incentives_bonuses'] ?? 0);
$overtime_rate     = (float)($_POST['overtime_rate']     ?? 0);
$epf_employee      = (float)($_POST['epf_employee']      ?? 8);
$epf_employer      = (float)($_POST['epf_employer']      ?? 12);
$etf               = (float)($_POST['etf']               ?? 3);
$tax_deduction     = (float)($_POST['tax_deduction']     ?? 0);
$apit_payee        = (float)($_POST['apit_payee']        ?? 0);
$loan_deductions   = (float)($_POST['loan_deductions']   ?? 0);
$other_deductions  = (float)($_POST['other_deductions']  ?? 0);

if (!$employee_id) {
    header('Location: salary_structures.php');
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO salary_structures
        (employee_id, basic_salary, housing_allowance, transport_allowance, medical_allowance, other_allowances,
         incentives_bonuses, overtime_rate, epf_employee, epf_employer, etf, tax_deduction, apit_payee, loan_deductions, other_deductions)
    VALUES
        (:eid, :basic, :housing, :transport, :medical, :other_allow,
         :incentives, :ot_rate, :epf_e, :epf_er, :etf, :tax, :apit, :loan, :other_ded)
    ON DUPLICATE KEY UPDATE
        basic_salary        = VALUES(basic_salary),
        housing_allowance   = VALUES(housing_allowance),
        transport_allowance = VALUES(transport_allowance),
        medical_allowance   = VALUES(medical_allowance),
        other_allowances    = VALUES(other_allowances),
        incentives_bonuses  = VALUES(incentives_bonuses),
        overtime_rate       = VALUES(overtime_rate),
        epf_employee        = VALUES(epf_employee),
        epf_employer        = VALUES(epf_employer),
        etf                 = VALUES(etf),
        tax_deduction       = VALUES(tax_deduction),
        apit_payee          = VALUES(apit_payee),
        loan_deductions     = VALUES(loan_deductions),
        other_deductions    = VALUES(other_deductions)
");
$stmt->execute([
    ':eid'        => $employee_id,
    ':basic'      => $basic_salary,
    ':housing'    => $housing_allowance,
    ':transport'  => $transport_allowance,
    ':medical'    => $medical_allowance,
    ':other_allow'=> $other_allowances,
    ':incentives' => $incentives_bonuses,
    ':ot_rate'    => $overtime_rate,
    ':epf_e'      => $epf_employee,
    ':epf_er'     => $epf_employer,
    ':etf'        => $etf,
    ':tax'        => $tax_deduction,
    ':apit'       => $apit_payee,
    ':loan'       => $loan_deductions,
    ':other_ded'  => $other_deductions,
]);

header('Location: salary_structures.php?msg=saved');
exit;
