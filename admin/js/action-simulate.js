/* Simulation form behaviour, shared by action-simulate.php and the workbench.
   Extracted from App\View\Action\SimulationFormView. */

/* Clone the last effect row (cleared) so admins can add name+value pairs. */
function addEffectRow(side) {
    var container = document.getElementById(side + '-effects');
    var rows = container.getElementsByClassName('effect-row');
    if (rows.length === 0) { return; }
    var clone = rows[rows.length - 1].cloneNode(true);
    clone.querySelectorAll('select, input').forEach(function (el) { el.value = ''; });
    container.appendChild(clone);
}
