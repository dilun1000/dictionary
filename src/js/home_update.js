import axios from 'axios';


let editingRowId = null;

// 1. Handle edit icon click
document.addEventListener('click', function (e) {
    if (e.target.closest('.edit-icon')) {
        const tr = e.target.closest('tr');
        editingRowId = tr.dataset.id; // assuming you set tr.dataset.id = word.id when creating the row
        document.getElementById('update-input').value = tr.querySelector('.td-eng').textContent.trim();
        document.getElementById('update-input_2').value = tr.querySelector('.td-rus').textContent.trim();
    }
});


document.getElementById('save-btn').addEventListener('click', function () {
    const eng = document.getElementById('update-input').value.trim();
    const rus = document.getElementById('update-input_2').value.trim();
    const messageDiv = document.getElementById('save-message');
    messageDiv.textContent = ''; // Clear previous message

    if (!eng || !rus) {
        messageDiv.textContent = 'Both fields are required.';
        return;
    }

    if (editingRowId) {
        axios.patch(`/dictionary/${editingRowId}`, { eng, rus })
            .then(() => {
                const tr = document.querySelector(`tr[data-id="${editingRowId}"]`);
                if (tr) {
                    tr.querySelector('.td-eng').textContent = eng;
                    tr.querySelector('.td-rus').textContent = rus;
                }
                editingRowId = null;
                document.getElementById('update-input').value = '';
                document.getElementById('update-input_2').value = '';
            })
            .catch(() => {
                messageDiv.textContent = 'Failed to update word.';
            });
    } else {
        
        axios.post('/dictionary', { eng, rus })
            .then(response => {
                console.log('SUCCESS:', response);

                // Clear input fields after successful store
                document.getElementById('update-input').value = '';
                document.getElementById('update-input_2').value = '';

                // Optionally, reset query for filter/autocomplete
                query = '';
                filterInput.value = '';

                // Optionally, refresh table or fetch newly added word
                fetchInitialScrollWords();
            })
            .catch(error => {
                console.log('ERROR:', error.response ? error.response.data : error.message);
            });            
    }
});