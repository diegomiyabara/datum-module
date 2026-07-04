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
    'jquery',
    'uiComponent'
], function ($, Component) {
    'use strict';

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
                    justUpdated: false
                });

            return this;
        },

        /**
         * @returns {Object} Chainable
         */
        initialize: function () {
            this._super();

            // tvery time the page is loaded, the first poll will always return a new version, so the qty is updated immediately
            this.version = '';

            // Subscribe to changes in the qty observable so the template can pulse the number on screen when it changes.
            this.qty.subscribe(this.pulse, this);

            this.refresh();

            // Poll the endpoint every refreshInterval seconds (minimum 5 seconds).
            setInterval(this.refresh.bind(this), Math.max(this.refreshInterval, 5) * 1000);

            return this;
        },

        /**
         * On failure the last known value is kept on screen; the next tick retries anyway.
         */
        refresh: function () {
            var self = this;

            // Endpoint returns a JSON with the current version, the qty, and a changed boolean.
            $.getJSON(this.refreshUrl, {version: this.version}).done(function (update) {
                self.version = update.version;

                if (update.changed) {
                    self.qty(Math.max(0, Math.floor(update.qty)));
                }
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
