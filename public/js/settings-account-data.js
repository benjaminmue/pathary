document.getElementById('deleteHistoryButton').addEventListener('click', async () => {
    const deleteHistoryModal = bootstrap.Modal.getInstance('#deleteHistoryModal');

    const response = await deleteUserHistory();

    deleteHistoryModal.hide()

    switch (response.status) {
        case 204:
            addAlert('alertDeletionsDiv', 'History deletion was successful', 'success');
            break;
        case 400:
            const errorMessage = await response.text();

            addAlert('alertDeletionsDiv', errorMessage, 'danger');

            break;
        default:
            addAlert('alertDeletionsDiv', 'Unexpected server error', 'danger');
    }
});

document.getElementById('deleteRatingsButton').addEventListener('click', async () => {
    const deleteRatingsModal = bootstrap.Modal.getInstance('#deleteRatingsModal');

    const response = await deleteUserRatings();

    deleteRatingsModal.hide()

    switch (response.status) {
        case 204:
            addAlert('alertDeletionsDiv', 'Ratings deletion successful', 'success');

            break;
        case 400:
            const errorMessage = await response.text();

            addAlert('alertDeletionsDiv', errorMessage, 'danger');

            break;
        default:
            addAlert('alertDeletionsDiv', 'Unexpected server error', 'danger');
    }
});

document.getElementById('deleteAccountButton').addEventListener('click', async () => {
    const deleteAccountModal = bootstrap.Modal.getInstance('#deleteAccountModal');

    const response = await deleteUserAccount();

    deleteAccountModal.hide()

    switch (response.status) {
        case 204:
            window.location.href = APPLICATION_URL + '/'

            break;
        case 400:
            const errorMessage = await response.text();

            addAlert('alertDeletionsDiv', errorMessage, 'danger');

            break;
        default:
            addAlert('alertDeletionsDiv', 'Unexpected server error', 'danger');
    }
});

function deleteUserAccount() {
    return fetch(APPLICATION_URL + '/old/settings/account/delete-account', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            '_csrf_token': getCsrfToken(),
        })
    })
}

function deleteUserHistory() {
    return fetch(APPLICATION_URL + '/old/settings/account/delete-history', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            '_csrf_token': getCsrfToken(),
        })
    })
}

function deleteUserRatings() {
    return fetch(APPLICATION_URL + '/old/settings/account/delete-ratings', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            '_csrf_token': getCsrfToken(),
        })
    })
}