// La búsqueda de usuarios se resolvía aquí ocultando filas ya
// renderizadas. Ahora el listado está paginado y busca en SQL
// (UsuarioModel::getFilteredList): un filtro de cliente solo habría
// alcanzado a las 25 filas de la página visible, dando a entender que el
// resto de coincidencias no existía. El campo de búsqueda es un input
// normal dentro de un formulario GET.
//
// Se deja el envío del formulario al pulsar Enter, que es el
// comportamiento nativo, sin JavaScript de por medio.

// Función global para eliminar usuarios (llamada desde onclick)
function deleteUser(id) {
    if (confirm('¿Estás seguro de que deseas desactivar este usuario? No podrá iniciar sesión, pero sus datos y registros se conservarán.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
