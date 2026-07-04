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
            refreshInterval: 15
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

            // KO only notifies subscribers when the primitive value actually changes,
            // so repeated polls returning the same qty never trigger the pulse
            this.qty.subscribe(this.pulse, this);

            this.refresh();
            setInterval(this.refresh.bind(this), Math.max(this.refreshInterval, 5) * 1000);

            return this;
        },

        /**
         * On failure the last known value is kept on screen; the next tick retries anyway.
         */
        refresh: function () {
            var self = this;

            $.getJSON(this.refreshUrl).done(function (qty) {
                self.qty(Math.max(0, Math.floor(qty)));
            });
        },

        /**
         * Runs via subscribe on qty: flags the template so the CSS pulse animation plays.
         */
        pulse: function () {
            this.justUpdated(true);
        },

        /**
         * Bound to animationend in the template — the CSS animation owns the duration,
         * so no JS timer is needed to end the pulse.
         */
        endPulse: function () {
            this.justUpdated(false);

            return true;
        }
    });
});
