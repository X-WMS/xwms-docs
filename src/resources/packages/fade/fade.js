class FadeSystem {
    constructor() {
        this.fadeDuration = 100;
        this.elements = $('[fade-element]');
        this.init();
    }

    init() {
        this.elements.each((index, el) => {
            const $el = $(el);
            $el.css('opacity', 0); // Zorgt ervoor dat het initieel verborgen is
            this.applyInitialTransform($el);
        });

        this.handleScroll();
        $(window).on('scroll resize', () => this.handleScroll());
    }

    applyInitialTransform($el) {
        const size = this.getSize($el);
        
        if ($el.is('[fade-top]')) $el.css('transform', `translateY(-${size}px)`);
        if ($el.is('[fade-bottom]')) $el.css('transform', `translateY(${size}px)`);
        if ($el.is('[fade-left]')) $el.css('transform', `translateX(-${size}px)`);
        if ($el.is('[fade-right]')) $el.css('transform', `translateX(${size}px)`);
        if ($el.is('[fade-scale]')) $el.css('transform', `scale(0.5)`);
        if ($el.is('[fade-rotate]')) $el.css('transform', `rotate(-20deg)`);
        if ($el.is('[fade-blur]')) $el.css('filter', `blur(10px)`);
    }

    getSize($el) {
        let size = 50; // Standaard waarde
        let attributes = $el[0].getAttributeNames(); // Haal alle attribuutnamen op
    
        attributes.forEach(attr => {
            if (attr.startsWith("fade-size-")) {
                let match = attr.match(/fade-size-(\d+)/);
                if (match) size = parseInt(match[1]);
            }
        });
    
        return size;
    }
    
    getDuration($el) {
        let duration = 0.8; // Standaard in seconden
        let attributes = $el[0].getAttributeNames();
    
        attributes.forEach(attr => {
            if (attr.startsWith("fade-duration-")) {
                let match = attr.match(/fade-duration-(\d+)/);
                if (match) duration = parseInt(match[1]) / 1000; // Omzetten naar seconden
            }
        });
    
        return duration;
    }
    
    getDelay($el) {
        let delay = 0; // Standaard geen vertraging
        let attributes = $el[0].getAttributeNames();
    
        attributes.forEach(attr => {
            if (attr.startsWith("fade-delay-")) {
                let match = attr.match(/fade-delay-(\d+)/);
                if (match) delay = parseInt(match[1]) / 1000;
            }
        });
    
        return delay;
    }
    
    getEase($el) {
        let easing = 'ease-out'; // Standaard easing
        let attributes = $el[0].getAttributeNames();
    
        attributes.forEach(attr => {
            if (attr.startsWith("fade-ease-")) {
                let match = attr.match(/fade-ease-(\w+)/);
                if (match) easing = match[1];
            }
        });
    
        return easing;
    }
    
    getTrigger($el) {
        let trigger = 90; // Standaard 85% van viewport
        let attributes = $el[0].getAttributeNames();
    
        attributes.forEach(attr => {
            if (attr.startsWith("fade-trigger-")) {
                let match = attr.match(/fade-trigger-(\d+)/);
                if (match) trigger = parseInt(match[1]);
            }
        });
    
        return trigger;
    }    

    handleScroll() {
        setTimeout(() => {
            this.elements.each((index, el) => {
                const $el = $(el);
                if (this.isInViewport($el)) {
                    this.fadeIn($el);
                } else if ($el.is('[fade-repeat]')) {
                    this.fadeOut($el);
                }
            });
        }, this.fadeDuration);
    }

    isInViewport($el) {
        const rect = $el[0].getBoundingClientRect();
        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        const triggerPercent = this.getTrigger($el) / 100;
        return rect.top <= windowHeight * triggerPercent && rect.bottom >= 0;
    }

    fadeIn($el) {
        $el.css({
            'opacity': 1,
            'transform': 'translateX(0) translateY(0) scale(1) rotate(0deg)',
            'filter': 'blur(0px)',
            'transition': `all ${this.getDuration($el)}s ${this.getEase($el)} ${this.getDelay($el)}s`
        });
    }

    fadeOut($el) {
        this.applyInitialTransform($el);
        $el.css({
            'opacity': 0,
            'transition': `all ${this.getDuration($el)}s ${this.getEase($el)}`
        });
    }
}

// Initialisatie van het systeem
$(document).ready(() => {
    new FadeSystem();
});
