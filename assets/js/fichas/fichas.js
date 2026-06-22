/**
 * Fichas Client Logic
 */
function deleteSheet(id) {
  if (confirm('¿Estás seguro de que deseas eliminar esta ficha? Esta acción no se puede deshacer.')) {
    const deleteIdInput = document.getElementById('deleteId');
    const deleteForm = document.getElementById('deleteForm');
    if (deleteIdInput && deleteForm) {
      deleteIdInput.value = id;
      deleteForm.submit();
    }
  }
}

// Búsqueda interactiva en las tarjetas
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('searchFichas');
  const estadoFilter = document.getElementById('filterEstado');
  const programaFilter = document.getElementById('filterPrograma');

  if (searchInput) searchInput.addEventListener('keyup', filterFichas);
  if (estadoFilter) estadoFilter.addEventListener('change', filterFichas);
  if (programaFilter) programaFilter.addEventListener('change', filterFichas);
});

function filterFichas() {
  const searchInput = document.getElementById('searchFichas');
  const estadoSelect = document.getElementById('filterEstado');
  const programaSelect = document.getElementById('filterPrograma');

  if (!searchInput || !estadoSelect || !programaSelect) return;

  const searchTerm = searchInput.value.toLowerCase();
  const estadoFilter = estadoSelect.value;
  const programaFilter = programaSelect.value;
  
  const cards = document.querySelectorAll('.ficha-card');
  cards.forEach(card => {
    const text = card.textContent.toLowerCase();
    const estado = card.dataset.estado;
    const programa = card.dataset.programa;
    
    const matchSearch = text.includes(searchTerm);
    const matchEstado = !estadoFilter || estado === estadoFilter;
    const matchPrograma = !programaFilter || programa === programaFilter;
    
    card.style.display = (matchSearch && matchEstado && matchPrograma) ? '' : 'none';
  });
}
