// FileManager JavaScript Functions
function switchTab(tabName) {
    console.log("Cambiando a pestaña:", tabName);
    
    // Recargar la página con el parámetro de pestaña correcto
    var url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.location.href = url.toString();
}

function showConfig() {
    window.location.href = window.dolibarrRoot + '/custom/filemanager/admin/setup.php';
}

function clearSearch() {
    var searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
        
        // Limpiar búsqueda en archivos
        var filesContainer = document.getElementById('filesContent');
        if (filesContainer) {
            var filesGrid = filesContainer.querySelector('.grid-cards');
            if (filesGrid) {
                var filesCards = filesGrid.querySelectorAll('.file-card');
                for (var i = 0; i < filesCards.length; i++) {
                    filesCards[i].style.display = 'flex';
                }
            }
        }
        
        // Limpiar búsqueda en papelera
        var trashContainer = document.getElementById('trashContent');
        if (trashContainer) {
            var trashGrid = trashContainer.querySelector('.grid-cards');
            if (trashGrid) {
                var trashCards = trashGrid.querySelectorAll('.file-card');
                for (var i = 0; i < trashCards.length; i++) {
                    trashCards[i].style.display = 'flex';
                }
            }
        }
        
        // Restablecer breadcrumb cuando se limpia la búsqueda
        var activeTab = document.querySelector('.file-manager-tab.active');
        updateBreadcrumb(activeTab && activeTab.id === 'trashTab' ? 'trash' : 'files');
    }
}

// Otras funciones JavaScript...
function openFolder(path) {
    window.location.href = '?path=' + encodeURIComponent(path);
}

function openFile(path) {
    if (isViewableFile(path)) {
        viewFile(path);
    } else {
        downloadFile(path);
    }
}

function isViewableFile(path) {
    var viewableExtensions = ['txt', 'js', 'css', 'html', 'php', 'json', 'xml', 'csv', 'md'];
    var extension = path.split('.').pop().toLowerCase();
    return viewableExtensions.indexOf(extension) !== -1;
}

function viewFile(path) {
    window.open('viewer.php?path=' + encodeURIComponent(path), '_blank');
}

function downloadFile(path) {
    window.location.href = 'download.php?action=download_file&path=' + encodeURIComponent(path);
}

function downloadAsZip(path) {
    Swal.fire({
        title: 'Generando ZIP...',
        text: 'Por favor espera mientras se crea el archivo ZIP',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    window.location.href = 'download.php?action=download_zip&path=' + encodeURIComponent(path);
}

function deleteItem(path, type) {
    if (confirm('¿Estás seguro de que quieres mover este ' + type + ' a la papelera?')) {
        // Implementar lógica de eliminación
        console.log('Deleting:', path, type);
    }
}

function restoreItem(name) {
    if (confirm('¿Estás seguro de que quieres restaurar este archivo?')) {
        // Implementar lógica de restauración
        console.log('Restoring:', name);
    }
}

function deletePermanentItem(name) {
    if (confirm('¿Estás seguro de que quieres eliminar permanentemente este archivo?')) {
        // Implementar lógica de eliminación permanente
        console.log('Permanently deleting:', name);
    }
}

function updateBreadcrumb(tabName) {
    var activeTab = document.querySelector('.file-manager-tab.active');
    var breadcrumbContainer = document.querySelector('.breadcrumb-container');
    if (!breadcrumbContainer) return;
    
    var rootPath = window.filemanagerRootPath;
    var tabParam = tabName;
    var icon = tabName === 'trash' ? '🗑️ ' : '🏠 ';
    var tabDisplayName = tabName === 'trash' ? 'Papelera' : 'Archivos';
    
    var breadcrumbHtml = 'Ruta actual: ';
    breadcrumbHtml += '<a href="?path=' + encodeURIComponent(rootPath) + '&tab=' + tabParam + '" class="breadcrumb-link">';
    breadcrumbHtml += icon + tabDisplayName;
    breadcrumbHtml += '</a>';
    
    breadcrumbContainer.innerHTML = breadcrumbHtml;
}

function updateBreadcrumbWithSearch(searchTerm, activeTab) {
    var breadcrumbContainer = document.querySelector('.breadcrumb-container');
    if (!breadcrumbContainer) return;
    
    if (searchTerm && searchTerm.length > 0) {
        var tabName = activeTab && activeTab.id === 'trashTab' ? 'Papelera' : 'Archivos';
        var rootPath = window.filemanagerRootPath;
        var tabParam = activeTab && activeTab.id === 'trashTab' ? 'trash' : 'files';
        var icon = activeTab && activeTab.id === 'trashTab' ? '🗑️ ' : '🏠 ';
        var breadcrumbHtml = 'Ruta actual: ';
        breadcrumbHtml += '<a href="?path=' + encodeURIComponent(rootPath) + '&tab=' + tabParam + '" class="breadcrumb-link">';
        breadcrumbHtml += icon + tabName;
        breadcrumbHtml += '</a>';
        breadcrumbHtml += ' / 🔍 Búsqueda: "' + searchTerm + '"';
        breadcrumbContainer.innerHTML = breadcrumbHtml;
    } else {
        updateBreadcrumb(activeTab && activeTab.id === 'trashTab' ? 'trash' : 'files');
    }
}

function sortFiles() {
    var sortBy = document.getElementById('sortBy').value;
    var activeTab = document.querySelector('.file-manager-tab.active');
    var container = activeTab && activeTab.id === 'filesTab' ? document.getElementById('filesContent') : document.getElementById('trashContent');
    if (!container) return;
    
    // Asegurar que el contenedor mantenga la vista en cuadrículas
    var gridContainer = container.querySelector('.grid-cards');
    if (!gridContainer) {
        // Si no existe el contenedor de cuadrícula, crearlo
        gridContainer = document.createElement('div');
        gridContainer.className = 'grid-cards';
        container.appendChild(gridContainer);
    }
    
    var cards = Array.from(gridContainer.querySelectorAll('.file-card'));
    
    cards.sort(function(a, b) {
        var nameA = a.querySelector('.file-card-name').textContent.toLowerCase();
        var nameB = b.querySelector('.file-card-name').textContent.toLowerCase();
        
        switch(sortBy) {
            case 'name':
                return nameA.localeCompare(nameB);
            case 'name-desc':
                return nameB.localeCompare(nameA);
            case 'date':
                return new Date(b.dataset.date) - new Date(a.dataset.date);
            case 'date-desc':
                return new Date(a.dataset.date) - new Date(b.dataset.date);
            case 'size':
                return parseInt(a.dataset.size) - parseInt(b.dataset.size);
            case 'size-desc':
                return parseInt(b.dataset.size) - parseInt(a.dataset.size);
            default:
                return 0;
        }
    });
    
    cards.forEach(function(card) {
        gridContainer.appendChild(card);
    });
}

function filterByType() {
    var filterType = document.getElementById('filterType').value;
    var activeTab = document.querySelector('.file-manager-tab.active');
    var container = activeTab && activeTab.id === 'filesTab' ? document.getElementById('filesContent') : document.getElementById('trashContent');
    if (!container) return;
    
    // Asegurar que el contenedor mantenga la vista en cuadrículas
    var gridContainer = container.querySelector('.grid-cards');
    if (!gridContainer) {
        gridContainer = document.createElement('div');
        gridContainer.className = 'grid-cards';
        container.appendChild(gridContainer);
    }
    
    var cards = gridContainer.querySelectorAll('.file-card');
    
    for (var i = 0; i < cards.length; i++) {
        var card = cards[i];
        var isFolder = card.classList.contains('folder-card');
        var isFile = card.classList.contains('file-card');
        
        var show = true;
        if (filterType === 'folders' && !isFolder) show = false;
        if (filterType === 'files' && !isFile) show = false;
        
        card.style.display = show ? 'flex' : 'none';
    }
}

function resetFilters() {
    document.getElementById('sortBy').value = 'name';
    document.getElementById('filterType').value = 'all';
    
    var activeTab = document.querySelector('.file-manager-tab.active');
    var container = activeTab && activeTab.id === 'filesTab' ? document.getElementById('filesContent') : document.getElementById('trashContent');
    if (!container) return;
    
    // Asegurar que el contenedor mantenga la vista en cuadrículas
    var gridContainer = container.querySelector('.grid-cards');
    if (!gridContainer) {
        gridContainer = document.createElement('div');
        gridContainer.className = 'grid-cards';
        container.appendChild(gridContainer);
    }
    
    var cards = gridContainer.querySelectorAll('.file-card');
    for (var i = 0; i < cards.length; i++) {
        cards[i].style.display = 'flex';
    }
    
    sortFiles();
}

// Modal functions
var currentFilePath = null;

function viewFileInModal(path) {
    currentFilePath = path;
    var modal = document.getElementById('fileViewerModal');
    var title = document.getElementById('fileViewerTitle');
    var body = document.getElementById('fileViewerBody');
    
    modal.style.display = 'block';
    title.textContent = 'Cargando...';
    body.textContent = 'Cargando contenido del archivo...';
    
    var fileName = path.split('/').pop() || path.split('\\').pop();
    title.textContent = 'Vista previa: ' + fileName;
    
    fetch('viewer.php?path=' + encodeURIComponent(path), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        body.textContent = data;
    })
    .catch(error => {
        body.textContent = 'Error al cargar el archivo: ' + error.message;
    });
}

function closeFileViewer() {
    var modal = document.getElementById('fileViewerModal');
    modal.style.display = 'none';
    currentFilePath = null;
}

function downloadCurrentFile() {
    if (currentFilePath) {
        downloadFile(currentFilePath);
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM cargado, inicializando FileManager...");
    
    var searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            var activeTab = document.querySelector('.file-manager-tab.active');
            var container = activeTab && activeTab.id === 'filesTab' ? document.getElementById('filesContent') : document.getElementById('trashContent');
            
            if (!container) return;
            
            // Asegurar que el contenedor mantenga la vista en cuadrículas
            var gridContainer = container.querySelector('.grid-cards');
            if (!gridContainer) {
                gridContainer = document.createElement('div');
                gridContainer.className = 'grid-cards';
                container.appendChild(gridContainer);
            }
            
            var cards = gridContainer.querySelectorAll('.file-card');
            var hasResults = false;
            
            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var name = card.querySelector('.file-card-name').textContent.toLowerCase();
                var shouldShow = name.indexOf(searchTerm) !== -1;
                
                card.style.display = shouldShow ? 'flex' : 'none';
                if (shouldShow) hasResults = true;
            }
            
            updateBreadcrumbWithSearch(searchTerm, activeTab);
        });
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('fileViewerModal');
        if (event.target === modal) {
            closeFileViewer();
        }
    };
});