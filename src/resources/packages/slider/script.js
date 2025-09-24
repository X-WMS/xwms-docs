class Slider {
    constructor(options) {
        this.options = $.extend({
            id: `slider-${Date.now()}`,
            title: '',
            description: '',
            icon: null, // kan HTML, een string met een class, of een URL zijn
            bgColor: 'linear-gradient(to right,rgb(72, 46, 83),rgb(20, 11, 32))',
            barClass: "primary",
            textColor: '#c8c8c8',
            duration: 10000,
            closable: true,
            manualRemove: false,
            container: '#sliderContainer'
        }, options);

        this.$slider = null;
    }

    create() {
        this.$slider = this.createWrapper();
        this.$slider.append(this.createLoader());
        this.$slider.append(this.createCloseButton());
        if (this.options.icon) this.$slider.append(this.createIcon());
        this.$slider.append(this.createTextSection());

        $(this.options.container).append(this.$slider);

        if (this.options.closable) {
            this.$slider.find('.closeSlider').on('click', () => this.remove());
        }

        return this;
    }

    show() {
        setTimeout(() => {
            this.$slider.css('transform', 'translateX(0%)');
            this.$slider.find('.loader-bar').css('width', '100%');
            if (!this.options.manualRemove) {
                setTimeout(() => this.remove(), this.options.duration);
            }
        }, 50);
    }

    remove() {
        this.$slider.css('transform', 'translateX(120%)');
        setTimeout(() => this.$slider.remove(), 400);
    }

    createWrapper() {
        const o = this.options;
        return $(`
            <div id="${o.id}" class="xwms-shared-notofication position-relative col-12 col-sm-6 col-md-5 col-lg-4 col-xl-3 col-xxl-3 col-x3l-3 col-x4l-2 p-3 pt-4 ms-auto d-flex flex-row rounded overflow-hidden mb-2 border border-bottom border-1 border-${o.barClass}"
                style="transform: translateX(120%); transition: transform 0.4s ease; background: ${o.bgColor}; color: ${o.textColor};box-shadow: 0px 4px 10px 5px rgb(0 0 0 / 0.2);"
                data-id="${o.id}" data-status="${o.status}">
            </div>
        `);
    }

    createLoader() {
        const o = this.options;
        return $(`
            <div class="loader col-12 position-absolute top-0 start-0">
                <div class="bg-gradient-${o.barClass} loader-bar"
                     style="height: 2px; width: 0%; transition: width ${o.duration}ms linear;"></div>
            </div>
        `);
    }

    createCloseButton() {
        if (!this.options.closable) return '';

        return $(`
            <div class="col-12 d-flex flex-row-reverse position-absolute top-0 end-0 p-2">
                <span class="mdi mdi-close closeSlider" style="cursor:pointer;" data-id="${this.options.id}"></span>
            </div>
        `);
    }

    createIcon() {
        const icon = this.options.icon;
        let html = '';
        const isValidUrl = str => {
            try {
                new URL(str);
                return true;
            } catch (_) {
                return false;
            }
        };
    
        if (typeof icon === 'string') {
            if (isValidUrl(icon) || icon.startsWith('/')) {
                // Afbeelding
                html = `<img src="${icon}" alt="icon" class="me-2" style="width: 24px; height: 24px; object-fit: contain;">`;
            } else if (icon.includes('<') && icon.includes('>')) {
                // HTML fragment
                html = icon;
            } else if (icon.includes('mdi')) {
                // Icon class
                html = `<span class="${icon} me-2" style="font-size: 24px;"></span>`;
            } else {
                // Onbekend
                console.warn(`[Slider] Ongeldige icon waarde: "${icon}"`);
                return null;
            }
        } else if (typeof icon === 'object') {
            // Ruwe HTML string (al als jQuery element of DOM-node)
            html = icon;
        }
    
        return $(`<div class="col-auto d-flex align-items-center">${html}</div>`);
    }
    

    createTextSection() {
        const { title, description } = this.options;
        return $(`
            <div class="col d-flex flex-column p-3 py-0 position-relative overflow-hidden">
                ${title ? `<h5 class="m-0 cursor-default">${title}</h5>` : ''}
                ${description ? `<p class="m-0 text-muted cursor-default">${description}</p>` : ''}
            </div>
        `);
    }
}

class HandleServerResponse extends Slider {
    constructor(options = {}) {
        super(options);
        this.statusPresets = {
            success: {
                title: 'Success',
                barClass: 'success',
                bgColor: 'linear-gradient(to right,rgb(97, 150, 120),rgb(14, 43, 33))',
                icon: 'mdi mdi-check-circle-outline'
            },
            error: {
                title: 'Something went wrong',
                barClass: 'danger',
                bgColor: 'linear-gradient(to right,rgb(124, 72, 72),rgb(48, 16, 16))',
                icon: 'mdi mdi-alert-circle-outline'
            },
            warning: {
                title: 'Warning',
                barClass: 'warning',
                bgColor: 'linear-gradient(to right,rgb(136, 121, 85),rgb(58, 52, 20))',
                icon: 'mdi mdi-alert-outline'
            },
            info: {
                title: 'Information',
                barClass: 'info',
                bgColor: 'linear-gradient(to right,rgb(85, 82, 138),rgb(15, 18, 44))',
                icon: 'mdi mdi-information'
            },
            fatal: {
                title: 'Fatal error',
                barClass: 'dark',
                bgColor: 'linear-gradient(to right,rgb(26, 26, 26),rgb(0, 0, 0))',
                icon: 'mdi mdi-alert-decagram-outline'
            }
        };
    }

    handleNotification(response) {
        if (!response || typeof response !== 'object') return console.warn('[HSR] Invalid response.');

        let { status, message } = response;
        let override = {
            title: null,
            icon: null,
            status: null,
        };

        // Parse hsr:: markers and process them
        const regex = /hsr::(title|img|status)::([^\s]+)/g;
        message = message.replace(regex, (match, type, value) => {
            const parsedValue = decodeURIComponent(value).replace(/___/g, ' ');
            if (type === 'title') override.title = parsedValue;
            else if (type === 'img') override.icon = parsedValue;
            else if (type === 'status') override.status = parsedValue;
            return '';
        }).trim();

        const resolvedStatus = override.status || status || 'info';
        const preset = this.statusPresets[resolvedStatus] || this.statusPresets['info'];

        const sliderOptions = {
            status: resolvedStatus,
            title: override.title || preset.title,
            description: message,
            icon: override.icon || preset.icon,
            bgColor: preset.bgColor,
            barClass: preset.barClass,
            textColor: '#fff',
            closable: true
        };

        const slider = new Slider(sliderOptions);
        slider.create().show();
    }
}

function handleSlider(options) {
    const slider = new Slider(options);
    slider.create().show();
}

function handle_notification(response) {
    const hsr = new HandleServerResponse();
    hsr.handleNotification(response);
}


window.handleSlider = handleSlider;
window.handle_notification = handle_notification;

// --------------------------------------
// exmaples

// handleSlider({
//     title: 'Nieuw bericht',
//     description: 'Je hebt een nieuw bericht van JoeyAK2',
//     icon: '<span class="mdi mdi-email-outline" style="font-size: 40px;"></span>',
//     duration: 5000,
//     manualRemove: true
// });

// handleNotification({
//     status: 'error',
//     message: 'Data failed to saved!'
// });

// handleNotification({
//     status: 'success',
//     message: 'Data saved successfully!'
// });