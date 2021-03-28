import {store} from 'basePath/src/store/store';

let currentOdds = {

    minValue: 1.01,

    relative: {
        '1': 'p',
        '2': 'l'
    },

    types: {
        'global': 1,
        'sport': 2,
        'market': 3,
        'league': 4,
        'event': 5,
    },

    formatOdds: function (odds) {
        return Math.round((odds + Number.EPSILON) * 100) / 100;
    },

    isNumeric: function (n) {
        return !isNaN(parseFloat(n)) && isFinite(n);
    },

    get: function (odds, eventStatus, marketId, sportId, leagueId, eventId) {
        let percent = 0;
        let configs = store.getters.getConfigLineData;
        let property = this.relative[eventStatus];

        if (!(odds)) {
            return odds;
        }

        Object.entries(this.types).forEach(([key, item]) => {
            try {
                switch (item) {
                    case this.types.global:
                        percent = configs[item][property];
                        break;
                    case this.types.sport:
                        percent = configs[item][sportId][property];
                        break;
                    case this.types.market:
                        percent = configs[item][marketId][property];
                        break;
                    case this.types.league:
                        percent = configs[item][leagueId][property];
                        break;
                    case this.types.event:
                        percent = configs[item][eventId][property];
                        break;
                }
            } catch (e) {
            }
        });

        if (typeof percent == 'undefined') {
            percent = 0;
        }

        let currentOdds = this.formatOdds(odds - (odds * percent));

        if (currentOdds < this.minValue) {
            currentOdds = this.minValue;
        }
        return currentOdds;
    }
};

export default currentOdds;