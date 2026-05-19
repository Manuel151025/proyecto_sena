document.addEventListener('DOMContentLoaded', function() {
    // Buscar usuarios
    const searchInput = document.getElementById('searchUsers');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            const filter = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});

// Función global para eliminar usuarios (llamada desde onclick)
function deleteUser(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
