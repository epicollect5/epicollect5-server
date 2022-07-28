import {
    FETCH_ENTRIES_STATS
} from 'config/actions';

const initialState = {
    overall: null,
    private: null,
    public: null,
    wasRejected: null,
    error:''
};

export default function entriesTodayReducer(state = initialState, action) {

    switch (action.type) {
         case FETCH_ENTRIES_STATS + '_PENDING':
         {
             return {
                 ...state
             };
         }
        case FETCH_ENTRIES_STATS + '_FULFILLED':
        {

            const stats = action.payload.data.data;
            const entriesToday = stats.entries.today.private + stats.entries.today.public;
            const branchEntriesToday = stats.branch_entries.today.private + stats.branch_entries.today.public;
            const entriesTodayOverall = entriesToday + branchEntriesToday;
            const entriesTodayPublic = stats.entries.today.public + stats.branch_entries.today.public;
            const entriesTodayPrivate = stats.entries.today.private + stats.branch_entries.today.private;

            return {
                ...state,
                wasRejected: false,
                overall: entriesTodayOverall,
                private: entriesTodayPublic,
                public: entriesTodayPrivate
            };
        }
        case FETCH_ENTRIES_STATS + '_REJECTED':
        {
            return {
                ...state,
                wasRejected: true,
                error: action.payload.message
            };
        }
        default:
            return state;
    }
}
