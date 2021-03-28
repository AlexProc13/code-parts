<template>
    <div class="formWrap authForm questionModal">
        <div class="formHeader flexCenter" style="position: relative">
            <h2>{{ $t("shortText.secret_questions") }}</h2>
            <closeBtn
                    width="10"
                    height="10"
                    @click.native="$store.commit('setQuestionsModal')"
            >
            </closeBtn>
        </div>
        <div class="questionWarning">
            <div>
                <span>{{ $t("shortText.warning") }}!</span>
                <p>
                    {{ $t("shortText.please_note_that_your_answers") }}
                </p>
            </div>
            <div class="questionWarningIcon">
                <svg version="1.1" id="Layer_1" width="22" height="26" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 122.88 122.88" enable-background="new 0 0 122.88 122.88" xml:space="preserve"><g><path fill-rule="evenodd" clip-rule="evenodd" d="M61.44,0c33.926,0,61.44,27.514,61.44,61.44c0,33.926-27.514,61.439-61.44,61.439 C27.513,122.88,0,95.366,0,61.44C0,27.514,27.513,0,61.44,0L61.44,0z M52.687,90.555H69.44v14.81H52.687V90.555L52.687,90.555z M69.431,82.96H52.691c-1.665-20.343-5.159-29.414-5.159-49.729c0-7.492,6.075-13.57,13.567-13.57s13.57,6.078,13.57,13.57 C74.67,53.535,71.13,62.633,69.431,82.96L69.431,82.96z"/></g></svg>
            </div>
        </div>

        <form @submit.prevent="setQuestions">
            <QuestionBlock
                    v-for="answer in answers"
                    v-bind:key="answer.id"
                    v-bind:answer="answer"
                    v-bind:listQuestions="listQuestions"
                    v-bind:onlyRead="false"
                    v-bind:formSubmited="formSubmited"
                    @check-form="checkForm('answers')"
            >
            </QuestionBlock>

            <div class="btnWrap">
                <button class="mainBtn" :class="{'btnCancel' : !allAnswered || buttonDisabled}">{{ $t("shortText.continue") }}</button>
            </div>
        </form>
    </div>
</template>


<script>
    import axios from 'axios';
    import debounce from "lodash/debounce";
    import QuestionBlock from './QuestionBlock.vue';
    import closeBtn from "basePath/src/components/spans/CloseBtn.vue";
    export default {
        name: 'SetQuestions',
        components: {
            QuestionBlock,
            closeBtn
        },
        data() {
            return {
                disabled: false,
                answers: [
                    {id: 1, answer: null, question: null, error: false, show: true, accepted: false},
                    {id: 2, answer: null, question: null, error: false, show: false, accepted: false},
                    {id: 3, answer: null, question: null, error: false, show: false, accepted: false},
                ],
                listQuestions: [],
                originListQuestions: [],
                buttonDisabled: false,
                formSubmited: false,
            }
        },
        created() {
            this.getQuestions();
        },
        computed: {
            answersQuestion() {
                return this.answers.map(answer => answer.question)
            },
            answersAnswer() {
                return this.answers.map(answer => answer.answer)
            },
            allAnswered() {
                return this.answers.every(el => el.accepted)
            }
        },
        watch: {
            answersAnswer: debounce(function (answers) {
                answers.forEach((item, index) => {
                    if (item != null && item.trim().length) {
                        this.answers[index].accepted = true;
                    } else {
                        this.answers[index].accepted = false;
                    }
                    if (item != null && item.trim().length && typeof this.answers[index + 1] !== 'undefined') {
                        this.answers[index + 1].show = true;
                    }
                });
            }, 1000),
            answersQuestion(questions) {
                let usedQuestions = [];
                questions.forEach((item) => {
                    if (item != null) {
                        usedQuestions.push(item.id);
                    }
                });
                //update list questions
                this.listQuestions = this.originListQuestions.filter(function (item) {
                    return !usedQuestions.includes(item.id);
                });
            }
        },
        methods: {
            checkForm(type) {
                if (this.formSubmited) {
                    if (type == 'answers') {
                        if (!this.answers.every(el => el.answer)) {
                            this.buttonDisabled = true;
                            return false
                        } else {
                            this.buttonDisabled = false;
                            return true
                        }
                    }
                }
            },
            getQuestions() {
                axios.get('users/password/get_questions')
                    .then(response => {
                        this.listQuestions = [...response.data.data];
                        this.originListQuestions = [...response.data.data];
                    })
                    .catch(err => {
                        console.log('some is wrong')
                    });
            },
            setQuestions() {
                let params = {
                    questions: [],
                };
                this.formSubmited = true;
                let errorStatus = null;
                this.answers.forEach((item) => {
                    if (!item.answer || !item.question) {
                        item.error = true;
                        errorStatus = true;
                    } else {
                        params.questions.push({
                            id: item.question.id,
                            answer: item.answer,
                        });
                    }
                });
                if (errorStatus != null) {
                    this.buttonDisabled = true;
                    return false;
                }
                this.$store.commit('setQuestionsModal', false);
                axios.post('users/password/set_questions', params)
                    .then(response => {
                        //close
                        if (response.data.status) {
                            //open deposit - success
                            this.$store.commit('setQuestionsModal', false);
                            this.$store.commit('setDepositModal');
                        }
                        this.formSubmited = false;
                    })
                    .catch(err => {
                        this.formSubmited = false;
                        console.log('some is wrong')
                    });
            }
        }
    }
</script>