import axios from "axios";
import ConfigApp from 'basePath/config/app';
import {store} from "basePath/src/store/store"

class TrackActionModern {
    constructor(tokenTime, timeRefreshToken, callback) {
        this.timeRefreshToken = timeRefreshToken;
        this.tokenTime = tokenTime;
        this.callback = callback;
        this.activeEvents();

        setInterval(()=>{
            if (store.state.authStatus === 'success') {
                this.handler();

            }
        }, ( this.timeRefreshToken/10) * 60 * 1000);
    }

    handler() {
        //limit - when we can check token
        const limit = this.timeRefreshToken * 60 * 1000;
        let now = new Date();
        let startToken = new Date(store.getters.getAuthDate);
        let finishToken = new Date(startToken.getTime() + this.tokenTime * 60 * 1000);
        let diff = finishToken - now;

        //console.log(finishToken, now, diff / 1000);

        if (diff < 0) {
            //for logging out
            //if user probably - didn't move
            this.callback();
            this.offCheck();
        }

        if (diff < limit && diff > 0) {
            //for extend session
            //make check if user made some action in the past
            if (this.checkIsNeeded()) {
                this.callback();
                this.offCheck();

            }
        }
    }

    activeEvents() {
        this.mouseMove();
        this.scroll();
        this.touch();
        this.click();
    }

    checkIsNeeded() {
        return store.getters.getAuthCheck;
    }

    setCheck() {
        if (store.state.authStatus === 'success') {
            if (this.checkIsNeeded() == false) {
                store.commit('setAuthCheck', true);
            }
        }
        return true;
    }

    offCheck() {
        store.commit('setAuthCheck', false);
        return true;
    }

    scroll() {
        window.addEventListener('scroll', () => {
            this.setCheck();
        });
    }

    //for device
    mouseMove() {
        window.addEventListener('mousemove', () => {
            this.setCheck();
        });
    }

    //for mobile
    touch() {
        window.addEventListener('touchstart', () => {
            this.setCheck();
        });
    }

    click() {
        window.addEventListener('keydown', () => {
            this.setCheck();
        });
    }

    //keydown
}

const callBack = () => {
    axios
        .get(`users/get_user`, {})
        .then(response => {})
        .catch(error => {
            console.log(error)
        })
};

const tokenTime = ConfigApp.timeToken;
const timeRefreshToken = ConfigApp.timeRefreshToken;
const trackActionModern = new TrackActionModern(tokenTime, timeRefreshToken, callBack);

export default trackActionModern;