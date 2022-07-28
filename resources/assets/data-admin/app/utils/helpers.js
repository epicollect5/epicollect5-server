const Helpers = {
    makeFriendlyNumber(num) {

        const SI_PREFIXES = ['', 'k', 'M', 'G', 'T', 'P', 'E'];

        //what tier? (determines SI prefix)
        const tier = Math.log10(num) / 3 | 0;

        //if zero, we don't need a prefix
        if (tier === 0) return num;

        //get prefix and determine scale
        const prefix = SI_PREFIXES[tier];
        const scale = Math.pow(10, tier * 3);

        //scale the num
        const scaled = num / scale;

        //format num and add prefix as suffix
        if (scaled % 100 === 0) {
            return scaled.toFixed(0) + prefix;
        }
        return scaled.toFixed(1) + prefix;
    },
    getPercentage(total, part) {

        if (total === 0) {
            return '0%';
        }

        return ((100 * part) / (total)).toFixed(0) + '%';
    },
    getQueryString(url) {
        const urlParts = url.split('?', 1); //split only on first occurrence
        return urlParts[urlParts.length - 1];
    }
};

export default Helpers;
