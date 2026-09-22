// Preserve native table semantics while exposing column labels in mobile cards.
// Run again when live results replace part of the document.
const prepareResponsiveContent = (root = document) => {
    root.querySelectorAll('table').forEach((table) => {
        const headers = [...table.querySelectorAll('thead tr:last-child th')];
        if (!headers.length || table.closest('.ranking-standings-wrap')) return;
        table.classList.add('responsive-table');
        table.setAttribute('role', 'table');
        table.querySelectorAll('tr').forEach((row) => row.setAttribute('role', 'row'));
        headers.forEach((header) => header.setAttribute('role', 'columnheader'));
        table.querySelectorAll('tbody tr').forEach((row) => {
            let column = 0;
            [...row.cells].forEach((cell) => {
                cell.setAttribute('role', 'cell');
                if (!cell.dataset.label) cell.dataset.label = headers[column]?.textContent.trim() || '';
                column += cell.colSpan;
            });
        });
    });
    // Older forms use sibling labels without a for/id pair. Keep their labels
    // clickable and available to assistive technology and enhanced selects.
    root.querySelectorAll('.field').forEach((field, index) => {
        const label = field.querySelector('label');
        const input = field.querySelector('input:not([type="hidden"]),select,textarea');
        if (!label || !input || label.htmlFor) return;
        if (!input.id) {
            let suffix = index;
            while (document.getElementById(`field-control-${suffix}`)) suffix++;
            input.id = `field-control-${suffix}`;
        }
        label.htmlFor = input.id;
    });
};
prepareResponsiveContent();
document.addEventListener('easykids:live-content-updated', (event) => {
    prepareResponsiveContent(event.detail?.target || document);
});
