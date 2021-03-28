<template>
    <div>
        <h3 class="bold">{{ provider.name }}</h3>
        <div class="depSelected">
            <p>{{ $t("shortText.d_coin_payments_email") }}</p>
            <div class="proFileItem">
                <input @keyup="clearEmail()" type="text" v-model="email" :placeholder="$t('shortText.your_email')">
            </div>

            <br>
            <div class="amountWrap">
                <p>{{this.$t("shortText.deposit_amount")}} ({{$store.state.currencySymbol}}): </p>
                <input
                        type="number"
                        @keypress="isNumber($event)"
                        @keyup="clearAmount()"
                        class="withoutArrows"
                        placeholder="Amount"
                        v-model.number="amount">
            </div>

            <br>
            <div v-if="errorHost || innerError" class="error_msg amountErr">
                <svg width="16" height="16" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0)">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                              d="M0.256836 16.5002C0.256836 7.81961 7.31912 0.757324 15.9999 0.757324C24.6806 0.757324 31.7429 7.81945 31.7429 16.5002C31.7429 25.181 24.6806 32.2433 15.9999 32.2433C7.31912 32.2433 0.256836 25.1808 0.256836 16.5002ZM16.0966 5.63592C14.8041 5.63592 13.9645 6.49558 13.9928 7.39687C14.0136 8.05181 14.0886 10.2608 14.2157 11.7161L14.8419 18.7059C14.9418 19.5561 15.0857 20.1818 15.2738 20.5892C15.4411 20.9518 15.7153 21.1504 16.0966 21.1882C16.4779 21.1505 16.7522 20.9518 16.9194 20.5892C17.1074 20.1818 17.2513 19.5561 17.3513 18.7059L17.9776 11.7161C18.1047 10.2608 18.1797 8.05181 18.2004 7.39687C18.2266 6.56315 17.3891 5.63592 16.0966 5.63592ZM18.1713 24.2062C18.1713 25.3539 17.2424 26.2844 16.0965 26.2844C14.9506 26.2844 14.0217 25.3539 14.0217 24.2062C14.0217 23.0585 14.9506 22.128 16.0965 22.128C17.2424 22.128 18.1713 23.0585 18.1713 24.2062Z"
                              fill="#eb4846"></path>
                    </g>
                </svg>
                <span class="">{{ errorHost || innerError }}</span>
            </div>

            <div class="btnWrap">
                <button @click="make()" :disabled="isButtonDisabled" class="mainBtn">{{ $t("shortText.continue") }}
                </button>
                <button @click="$store.commit('setDepositModal')" class="mainBtn btnCancel">{{ $t("shortText.cancel")
                    }}
                </button>
            </div>
        </div>
    </div>
</template>
<script>
    import {mapGetters} from "vuex";
    import Helper from 'basePath/modules/helpers';

    export default {
        name: 'DepositCoinPayments',
        props: ['error', 'provider', 'isButtonDisabled'],
        components: {},
        data() {
            return {
                //we can full this object
                amount: null,
                email: Helper.checkEmailRegExp(this.email) ? this.email : null,
                errorHost: null,
                innerError: null,
                formSubmitted: false,
                invalidEmail: false,
                userData: {}
            }
        },
        created() {
            this.email = this.getUser;
        },
        computed: {
            ...mapGetters(['getUser'])
        },
        watch: {
            error(err) {
                this.errorHost = err
            }
        },
        methods: {
            isNumber(event) {
                let pattern = new RegExp(this.patternInput());
                if (!pattern.test(event.key)) return event.preventDefault();
            },
            clearEmail() {
                this.innerError = null;
                this.errorHost = null;
            },
            clearAmount() {
                this.innerError = null;
                this.errorHost = null;
            },
            validateForm() {
                if (!Helper.checkEmailRegExp(this.email)) {
                    this.innerError = this.$t("shortText.email_error");
                    return false;
                }
                if (!this.amount) {
                    this.innerError = this.$t("shortText.deposit_wrong_amount");
                    return false;
                }
                return true;
            },
            make() {
                //to do validate
                if (!this.validateForm()) {
                    return false;
                }
                this.userData.email = this.email;
                this.userData.amount = this.amount;
                //emmit event with userData variable
                this.$emit('make-deposit', this.userData);
            }
        }
    }
</script>