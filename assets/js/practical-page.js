document.addEventListener('DOMContentLoaded', () => {
    const dataTable = document.getElementById('data-table');
    const reportId = window.reportId;
    const practicalId = window.practicalId;

    // Auto-save logic for editable fields
    const autoSave = (field, value) => {
        console.log(`Auto-saving ${field}: ${value}`);
        
        const data = {
            observations: getObservationsData(),
            calculations: document.getElementById('calculations').value,
            result: document.getElementById('results').value,
            conclusion: document.getElementById('conclusion').value
        };

        fetch(`/smart-lab/api/v1/practicals.php?id=${practicalId}&action=save-draft`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Draft saved successfully');
            } else {
                console.error('Failed to save draft:', data.error);
            }
        })
        .catch(error => {
            console.error('Error saving draft:', error);
        });
    };

    const getObservationsData = () => {
        const rows = dataTable.querySelectorAll('tbody tr');
        const observations = [];
        
        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('td');
            observations.push({
                trial: cells[0].textContent.trim(),
                initial_reading: cells[1].textContent.trim(),
                final_reading: cells[2].textContent.trim(),
                volume_used: cells[3].textContent.trim()
            });
        });
        
        return observations;
    };

    // Add event listeners for contenteditable fields
    dataTable.addEventListener('input', (event) => {
        if (event.target.isContentEditable) {
            autoSave('observations', getObservationsData());
        }
    });

    const textareas = document.querySelectorAll('textarea');
    textareas.forEach((textarea) => {
        textarea.addEventListener('input', (event) => {
            autoSave(event.target.id, event.target.value);
        });
    });

    // Submit report
    const submitButton = document.createElement('button');
    submitButton.textContent = 'Submit Report';
    submitButton.style.cssText = 'margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;';
    submitButton.addEventListener('click', () => {
        const data = {
            observations: getObservationsData(),
            calculations: document.getElementById('calculations').value,
            result: document.getElementById('results').value,
            conclusion: document.getElementById('conclusion').value
        };

        fetch(`/smart-lab/api/v1/practicals.php?id=${practicalId}&action=submit-report`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Report submitted successfully!');
                window.location.href = '/smart-lab/student/dashboard';
            } else {
                alert('Failed to submit report: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error submitting report:', error);
            alert('Error submitting report');
        });
    });

    document.body.appendChild(submitButton);
});