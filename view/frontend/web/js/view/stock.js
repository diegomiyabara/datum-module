/**
 * Miyabara_FeaturedProduct
 *
 * Polls the salable qty endpoint and updates a Knockout observable, so only
 * the number on screen changes — never the page.
 *
 * @copyright © 2026 Diego M. Miyabara. All rights reserved.
 * @author    Diego M. Miyabara <diego.miyabara@gmail.com>
 */
define([
    'mage/storage',
    'uiComponent'
], function (storage, Component) {
    'use strict';

    var MAX_FAILURES = 3;

    return Component.extend({
        defaults: {
            refreshUrl: '',
            refreshInterval: 15 //default value  in case the config is not set
        },

        /**
         * Declares the reactive state; uiElement runs this hook as part of initialize.
         *
         * @returns {Object} Chainable
         */
        initObservable: function () {
            this._super()
                .observe({
                    qty: null,
                    justUpdated: false,
                    unavailable: false
                });

            return this;
        },

        /**
         * @returns {Object} Chainable
         */
        initialize: function () {
            this._super();

            // Every time the page is loaded, the first poll will always return a new version, so the qty is updated immediately
            this.version = '';
            this.failures = 0;
            this.pending = false;

            // Subscribe to changes in the qty observable so the template can pulse the number on screen when it changes.
            this.qty.subscribe(this.pulse, this);

            this.refresh();

            // Poll the endpoint every refreshInterval seconds (minimum 5 seconds).
            setInterval(this.refresh.bind(this), Math.max(this.refreshInterval, 5) * 1000);

            // Hidden tabs skip their ticks, so refresh as soon as the tab becomes visible again.
            document.addEventListener('visibilitychange', this.onVisibilityChange.bind(this));

            return this;
        },

        /**
         * Refreshes immediately when the tab regains visibility.
         */
        onVisibilityChange: function () {
            if (!document.hidden) {
                this.refresh();
            }
        },

        /**
         * Hidden tabs and in-flight requests skip the tick: no wasted calls, no request pile-up.
         * On failure the last known value is kept on screen; the next tick retries anyway.
         */
        refresh: function () {
            var self = this,
                headers = {};

            if (document.hidden || this.pending) {
                return;
            }

            this.pending = true;

            if (this.version) {
                headers['If-None-Match'] = '"' + this.version.replace(/["\\]/g, '\\$&') + '"';
            }

            // Endpoint returns a JSON with the current version, the qty, and a changed boolean.
            storage.get(this.refreshUrl + '?version=' + encodeURIComponent(this.version), false, undefined, headers)
                .done(function (update, textStatus, jqXHR) {
                    self.failures = 0;
                    self.unavailable(false);

                    if (jqXHR.status === 304) {
                        return;
                    }

                    self.version = update.version;

                    if (update.changed) {
                        self.qty(Math.max(0, Math.floor(update.qty)));
                    }
                })
                .fail(function () {
                    self.failures++;

                    // With nothing to show and the endpoint repeatedly failing, hide the badge
                    // instead of spinning on the loading state forever.
                    if (self.failures >= MAX_FAILURES && self.qty() === null) {
                        self.unavailable(true);
                    }
                })
                .always(function () {
                    self.pending = false;
                });
        },

        /**
         * Runs via subscribe on qty: flags the template so the CSS pulse animation plays.
         */
        pulse: function () {
            this.justUpdated(true);
        },

        /**
         * Resets the justUpdated observable so the CSS pulse animation can play again on the next change.
         */
        endPulse: function () {
            this.justUpdated(false);

            return true;
        }
    });
});
