// Initialize IndexedDB
const dbName = 'expressPadalaDB';
const dbVersion = 1;

let db;
const request = indexedDB.open(dbName, dbVersion);

request.onerror = event => {
    console.error('IndexedDB error:', event.target.error);
};

request.onsuccess = event => {
    db = event.target.result;
    console.log('IndexedDB connected');
};

request.onupgradeneeded = event => {
    const db = event.target.result;
    
    // Create object stores for offline data
    const documentsStore = db.createObjectStore('documents', { keyPath: 'id', autoIncrement: true });
    const transitStore = db.createObjectStore('transits', { keyPath: 'id', autoIncrement: true });
    const deliveriesStore = db.createObjectStore('deliveries', { keyPath: 'id', autoIncrement: true });
    
    // Create indexes
    documentsStore.createIndex('sync_status', 'sync_status');
    transitStore.createIndex('sync_status', 'sync_status');
    deliveriesStore.createIndex('sync_status', 'sync_status');
};

// Function to save data offline
async function saveOfflineData(storeName, data) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        
        data.sync_status = 'pending';
        const request = store.add(data);
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Function to sync data with server
async function syncData() {
    if (!navigator.onLine) return;

    const stores = ['documents', 'transits', 'deliveries'];
    
    for (const storeName of stores) {
        const transaction = db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const index = store.index('sync_status');
        
        const pendingData = await new Promise((resolve, reject) => {
            const request = index.getAll('pending');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        
        for (const item of pendingData) {
            try {
                const response = await fetch(`/api/sync.php?store=${storeName}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(item)
                });
                
                if (response.ok) {
                    item.sync_status = 'synced';
                    store.put(item);
                }
            } catch (error) {
                console.error(`Error syncing ${storeName}:`, error);
            }
        }
    }
}

// Function to generate reference number
function generateReferenceNumber() {
    const timestamp = new Date().getTime().toString().slice(-6);
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    return `REF-${timestamp}-${random}`;
}

// Event listener for form submissions
document.addEventListener('submit', async function(e) {
    if (e.target.classList.contains('offline-form')) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        try {
            if (!navigator.onLine) {
                await saveOfflineData(e.target.dataset.store, data);
                alert('Data saved offline. Will sync when online.');
            } else {
                const response = await fetch(e.target.action, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                alert('Data saved successfully');
            }
        } catch (error) {
            console.error('Error saving data:', error);
            await saveOfflineData(e.target.dataset.store, data);
            alert('Error occurred. Data saved offline.');
        }
    }
}); 