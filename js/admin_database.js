/**
 * js/admin_database.js - Table sorting and edit/delete modal interactions for database manager.
 */

const sortDirections = {};

function sortTable(columnIndex) {
    const table = document.getElementById("voslocatiesTable");
    if (!table) return;
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);

    sortDirections[columnIndex] = !sortDirections[columnIndex];
    const isAscending = sortDirections[columnIndex];

    rows.sort((a, b) => {
        const cellA = a.cells[columnIndex].textContent.trim();
        const cellB = b.cells[columnIndex].textContent.trim();

        const numA = parseFloat(cellA);
        const numB = parseFloat(cellB);
        if (!isNaN(numA) && !isNaN(numB)) {
            return isAscending ? numA - numB : numB - numA;
        }

        const dateA = Date.parse(cellA);
        const dateB = Date.parse(cellB);
        if (!isNaN(dateA) && !isNaN(dateB)) {
            return isAscending ? dateA - dateB : dateB - dateA;
        }

        return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
    });

    tbody.innerHTML = "";
    rows.forEach((row) => tbody.appendChild(row));
}

function openEditModal(data) {
    if (document.getElementById("edit_id")) document.getElementById("edit_id").value = data.id || '';
    if (document.getElementById("edit_type")) document.getElementById("edit_type").value = data.type || '';
    if (document.getElementById("edit_deelgebied")) document.getElementById("edit_deelgebied").value = data.deelgebied || '';
    if (document.getElementById("edit_ingestuurd_op")) document.getElementById("edit_ingestuurd_op").value = data.ingestuurd_op ? data.ingestuurd_op.replace(' ', 'T').substring(0, 16) : '';
    if (document.getElementById("edit_coord_x")) document.getElementById("edit_coord_x").value = data.coordinaat_x || '';
    if (document.getElementById("edit_coord_y")) document.getElementById("edit_coord_y").value = data.coordinaat_y || '';
    if (document.getElementById("edit_coordinaat_x")) document.getElementById("edit_coordinaat_x").value = data.coordinaat_x || '';
    if (document.getElementById("edit_coordinaat_y")) document.getElementById("edit_coordinaat_y").value = data.coordinaat_y || '';
    if (document.getElementById("edit_code")) document.getElementById("edit_code").value = data.code || '';
    if (document.getElementById("edit_opmerking")) document.getElementById("edit_opmerking").value = data.opmerking || '';
    
    openModal("editModal");
}

function openDeleteModal(id) {
    const deleteLink = document.getElementById("deleteLink");
    if (deleteLink) deleteLink.href = `../functies.php?verwijder_voslocatie=${id}`;
    openModal("deleteModal");
}
