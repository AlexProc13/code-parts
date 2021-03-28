const fs = require('fs');
const axios = require('axios');
const envData = require('dotenv').config();

module.exports = class loadBuildData {
    constructor() {
        this.countAttemps = 0;
    }

    action(callback) {
        let self = this;
        let gottenData = {DEFAULT_THEME: 'dark'};
        const url = `${envData.parsed.PLATFORM_DATA}`;
        if (this.countAttemps == 0 && url != 'undefined') {
            axios({
                method: 'get',
                url: envData.parsed.PLATFORM_DATA,
                headers: {
                    'platform-id': url,
                }
            }).then(function (response) {
                const dir = './extraData';
                if (!fs.existsSync(dir)) {
                    fs.mkdirSync(dir);
                }

                let write = {};
                if (Object.keys(response.data.data).length > 0) {
                    write = response.data.data;
                }

                fs.writeFile("./extraData/build.json", JSON.stringify(write), function (err) {
                    callback();
                });

                self.countAttemps = self.countAttemps + 1
            }).catch((error) => {
                callback(new Error(error));
            });

            self.countAttemps = self.countAttemps + 1
        } else {
            fs.writeFile("./extraData/build.json", JSON.stringify({}), function (err) {
                callback();
            });
        }
    }

    apply(compiler) {
        //for npm run dev
        compiler.hooks.beforeRun.tapAsync({name: 'LoadBuildData'}, (compilation, callback) => {
            this.action(callback);
        });

        //for npm run build
        compiler.hooks.watchRun.tapAsync({name: 'LoadBuildData'}, (compilation, callback) => {
            this.action(callback);
        });
    }
};