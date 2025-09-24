// ------------------------------------------------------
// --------- MAIN FUNCTIE
// ------------------------------------------------------

function sendRequest(url, method = 'GET', data = null, settings = {}) {
    return new Promise((resolve, reject) => {
    
        settings = settings || {};

        if (data === null) {
            data = {};
        }

        if (settings.formpost) {
            // Maak een verborgen formulier aan en stuur het als een normale form post
            const form = document.createElement('form');
            form.method = method;
            form.action = url;
            form.style.display = 'none';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }

            // Voeg de data toe als verborgen inputvelden
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    const value = data[key];
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
            }

            document.body.appendChild(form);
            form.submit();
            return; // Geen verdere verwerking nodig, omdat de pagina zal herladen
        }


        // 🔹 AJAX MODE (regular or multipart)
        let ajaxData = data;

        let ajaxOptions = {
            url: url,
            type: method,
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            success: function (response) {
                try {
                    const responseJson = JSON.parse(response) ?? response;
                    resolve(responseJson);
                } catch (jsonError) {
                    resolve(response);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                try {
                    const responseJson = JSON.parse(jqXHR.responseText) ?? jqXHR.responseText;
                    resolve(responseJson);
                } catch (jsonError) {
                    console.error(jsonError);
                    // resolve(jqXHR.responseText);
                }
            }
        };

        ajaxOptions.data = ajaxData;
        $.ajax(ajaxOptions);
    });
}

// ------------------------------------------------------
// --------- HELPER FUNCTIES
// ------------------------------------------------------

function sendRequestWithResponse(url, updateData, settings = {}) {
    return sendRequest(url, 'POST', updateData, settings)
        .then(response => {
            if (response) handle_notification(response);
            return response;
        })
        .catch(error => {
            console.error('Error while sending request:', error);
            alert('An error occurred while saving the event. Please try again.');
        });
}

function confirmTableAndExecute(action, parameters, message) {
    if (confirm(message)) {
        // Bepaal dynamisch hoe de parameters moeten worden doorgegeven
        if (!parameters){
            action();
        }else{
            const paramValues = Object.values(parameters); // Alle waarden in een array stoppen
            action(...paramValues); // Voer de actie uit met de dynamisch bepaalde parameters
        }
    }
}

function getInputData(id, selectors, type = false) {
    const data = {};
    // console.log(id, type, selectors);
    for (const [key, selector] of Object.entries(selectors)) {

        let value = null;
        if (type === false){
            value = $(`${selector}[data-id="${id}"]`).val();
        }else{
            value = $(`${selector}[data-id="${id}"][data-type="${type}"]`).val();
        }

        if (value?.trim()) {
            data[key] = value;
        }
    }
    return data;
}

function sendMultipartRequest(url, method = 'POST', selector = '[data-name]', debug = false) {
    if (debug) {
        // Debugmodus: doe gewone form post met file upload
        const form = document.createElement('form');
        form.method = method;
        form.action = url;
        form.enctype = 'multipart/form-data';
        form.style.display = 'none';

        // Voeg CSRF-token toe
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }

        // Voeg inputs toe
        $(selector).each(function () {
            const key = $(this).data('name');
            const type = $(this).attr('type');

            if (type === 'file') {
                const files = this.files;
                for (let i = 0; i < files.length; i++) {
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = files.length === 1 ? key : `${key}[${i}]`;
                    fileInput.files = this.files; // ❗ Werkt visueel niet in browser, dus:
                    // → Maak zichtbaar <input type="file"> met user-interactie nodig (browserbeperking)
                    // Oplossing: zeg tegen dev om handmatig op verzendknop te drukken
                    alert("⚠️ In debug-modus kunnen bestanden niet via JS worden toegevoegd aan form. Gebruik echte form met inputs.");
                }
            } else {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = $(this).val();
                form.appendChild(input);
            }
        });

        document.body.appendChild(form);
        form.submit();
        return;
    }

    // 🔁 Normale AJAX multipart
    return new Promise((resolve, reject) => {
        const formData = new FormData();

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            formData.append('_token', csrfToken);
        }

        $(selector).each(function () {
            const key = $(this).data('name');
            const type = $(this).attr('type');

            if (type === 'file') {
                const files = this.files;
                for (let i = 0; i < files.length; i++) {
                    formData.append(`${key}[${i}]`, files[i]);
                }
            } else {
                formData.append(key, $(this).val());
            }
        });

        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                try {
                    const responseJson = JSON.parse(response) ?? response;
                    resolve(responseJson);
                } catch (jsonError) {
                    resolve(response);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                try {
                    const responseJson = JSON.parse(jqXHR.responseText) ?? jqXHR.responseText;
                    resolve(responseJson);
                } catch (jsonError) {
                    console.error(jsonError);
                    resolve(jqXHR.responseText);
                }
            }
        });
    });
}



window.sendRequest = sendRequest;
window.sendRequestWithResponse = sendRequestWithResponse;
window.confirmTableAndExecute = confirmTableAndExecute;
window.getInputData = getInputData;
window.sendMultipartRequest = sendMultipartRequest;