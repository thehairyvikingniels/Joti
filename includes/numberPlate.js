/* De KentekenCheck is gebaseerd op de actuele (2023) formats(alle afgegeven kentekencombinaties) uitgegeven door de RDW, welke lijst is te vinden via bijgevoegde link. De oplossing kan ook ingezet worden als HTML5 validation only in het 'pattern' attribuut, zie html.
De open data API vd RDW retourneert geen koppeltekens in het kenteken voor zover bekend, dus vandaar deze oplossing.
De array van regex patronen correspondeert met de lijst van formats op de site vd RDW in bijgaande link.
De class declaratie 'KentekenCheck' retourneert een valide NL kenteken, er worden in de latere series geen klinkers gebruikt en geen tekens die de RDW voorschrijft. Kentekens met AA en CD zijn in deze functie niet meegenomen, de letters C en Q mogen niet meer vd overheid ivm interpretatie problemen en zijn wel meegenomen. Letters L en T niet meer vanaf serie 11.
Babel is mogelijk nodig voor ondersteuning van legacy browsers zoals IE 10 /IE 11/Edge 13 etc.

Verboden combinaties: GVD, KKK, KVT, LPF, NSB, PKK, PSV, TBS, SS en SD (ook niet in lettercombinaties met 3 letters)
Vanaf serie 11: PVV, SGP en VVD verboden

MIT License
Copyright (c) 2023 https://github.com/friedt
*/

const inputElm = document.getElementById('input-kenteken');
kentekenKnop = document.getElementById("kentekenKnop");

class KentekenCheck {

    constructor(kenteken = '', inputElm = null, outputElm = null, output = false, classValid = 'valid') {
        this.newStr = '';
        this.output = output;
        this.kenteken = kenteken;
        this.index = 0;
        this.valid = false;
        this.inputElm = inputElm;
        this.outputElm = outputElm;
        this.classValid = classValid;
        this.arrRegEx = ['^([A-Z]|[^0-9CIOY]{2})([0-9]{2})([0-9]{2})$', // XX9999 1951
            '^([0-9]{2})([0-9]{2})([A-Z]|[^0-9CIOY]{2})$', // 9999XX 1965
            '^([0-9]{2})([A-Z]|[^0-9CIOY]{2})([0-9]{2})$', // 99XX99 1973
            '^([BDFGHJKLMNPRSTVWXYZ]{2})([0-9]{2})([BDFGHJKLMNPRSTVWXYZ]{2})$',// XX99XX 1978
            '^([BDFGHJKLMNPRSTVWXZ]{2})([BDFGHJKLMNPRSTVWXZ]{2})([0-9]{2})$',// XXXX99 1991
            '^([0-9]{2})([BDFGHJKLMNPRSTVWXZ]{2})([BDFGHJKLMNPRSTVWXZ]{2})$',// 99XXXX 1999
            '^([0-9]{2})([BDFGHJKLMNPRSTVWXZ]{3})([0-9]{1})$',// 99XXX9 2005
            '^([0-9]{1})([BDFGHJKLMNPRSTVWXZ]{3})([0-9]{2})$',// 9XXX99 2009
            '^([BDFGHJKLMNPRSTVWXZ]{2})([0-9]{3})([BDFGHJKLMNPRSTVWXZ]{1})$',// XX999X 2006
            '^([BDFGHJKLMNPRSTVWXZ]{1})([0-9]{3})([BDFGHJKLMNPRSTVWXZ]{2})$',// X999XX 2008
            '^([BDFGHJKLMNPRSTVWXZ]{3})([0-9]{2})([BDFGHJKLMNPRSTVWXZ]{1})$',// XXX99X 11 2015
            '^([BDFGHJKMNPRSVWXZ]{1})([0-9]{2})([BDFGHJKLMNPRSTVWXZ]{3})$',// X99XXX 12 2021
            '^([0-9]{1})([BDFGHJKLMNPRSTVWXZ]{2})([0-9]{3})$',//9XX999 13 2016
            '^([0-9]{3})([BDFGHJKLMNPRSTVWXZ]{2})([0-9]{1})$'//999XX9 14 2019
        ];

        this.forbiddenCharacters = /^((?!GVD|KKK|KVT|LPF|NSB|PKK|PSV|TBS|SS|SD|PVV|SGP|VVD).){8}$/;
    }

    formatLicense() {
        if (typeof this.kenteken !== 'string') return;

        const str = this.kenteken.toUpperCase()
            .trim()
            .split('-')
            .join(''); // trim whitespace / strip dashes
        return this.showLicense(str);
    }

    matchLicense(str) {
        return this.arrRegEx.some((regEx, i) => {

            const re = new RegExp(regEx);
            const result = re.test(str);


            // match on regex pattern
            if (result) {
                this.index = i;
                return true;
            }
        });
    }

    checkForbiddenCharacters(str) {
        return this.forbiddenCharacters.test(str);
    }

    showLicense(str) {

        // based on rdw demands
        // returns true immediately when found match : legacy browser proof IE 9/10/11, no polyfill needed
        const matchLicense = this.matchLicense(str);

        if (matchLicense) {
            this.valid = matchLicense;
            const re = new RegExp(this.arrRegEx[this.index]);
            if (this.inputElm !== null) {
                this.inputElm.value = str.replace(re, '$1-$2-$3');
                this.inputElm.classList.add(this.classValid);
            }
            this.newStr = str.replace(re, '$1-$2-$3');

            const notForbidden = this.checkForbiddenCharacters(this.newStr)
            if (notForbidden) {
                this.showInContainer(this.newStr);
                kentekenKnop.classList.remove("w3-disabled");
                kentekenKnop.disabled = false;
                return this.newStr;
            }

        }
        if (this.inputElm !== null) {
            this.inputElm.classList.remove(this.classValid);
        }
        this.valid = false;
        kentekenKnop.classList.add("w3-disabled");
        kentekenKnop.disabled = true;
        this.showInContainer('XX-XX-XX')
        return 'XX-XX-XX';
    }

    showInContainer(str) {
        if (this.output && this.outputElm !== null) {
            this.outputElm.innerHTML = str;
        }
    }

    getValue(e) {
        if (e.target.value.length >= 6) {
            this.kenteken = e.target.value;
            this.formatLicense();
        }

    }

    bindInputListener(event = 'input') {
        if (this.inputElm !== null){
            this.inputElm.addEventListener(event, this.getValue.bind(this));
        }
    }

}

// vervang het voorbeeld met een geldig kenteken zonder/met geplaatste koppeltekens
// bijvoorbeeld 12TTHJ HFFF43 of 1KGF55 of G234TR H222GG, HF-FF43 , G-234-TR


const kt = new KentekenCheck('007-JB-1', inputElm);
kt.formatLicense();
kt.bindInputListener();



