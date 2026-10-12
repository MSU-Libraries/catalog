#include <iostream>
#include <fstream>
#include <string>
#include <vector>
#include <unordered_map>
#include <chrono>
#include <algorithm>
#include <cstdlib>
#include <cstring>
#include <getopt.h>
#include <unistd.h>
#include <arpa/inet.h>
#include <list>

using namespace std;

enum FilterAction { NONE, ALLOW, FILTER };
enum IPClass { CLS_ALLOW, CLS_FILTER, CLS_OTHER };

struct MasterRecord {
    string cidr;
    string asn;
    string cc;
    uint32_t ip;
    int prefix;
};

struct OutputRecord {
    string asn;
    string cc;
};

struct CacheEntry {
    string ip;
    string result;
    chrono::steady_clock::time_point expires_at;
};

unordered_map<string, FilterAction> cc_filters;
unordered_map<string, FilterAction> asn_filters;

const uint32_t TBL8_FLAG = 0x80000000U;
const uint32_t RECORD_MASK = 0x7FFFFFFFU;

vector<uint32_t> tbl24(16777216, 0);
vector<vector<uint32_t>> tbl8_groups;

vector<OutputRecord> records = { {"", ""} };

bool verbose = false;
bool debug_mode = false;

// Parse IPv4 CIDR string to integer and prefix length
bool parse_cidr(const string& cidr_str, uint32_t& ip_out, int& prefix_out) {
    auto pos = cidr_str.find('/');
    string ip_str = cidr_str.substr(0, pos);
    prefix_out = (pos != string::npos) ? stoi(cidr_str.substr(pos + 1)) : 32;

    struct in_addr addr;
    if (inet_pton(AF_INET, ip_str.c_str(), &addr) != 1) return false;
    ip_out = ntohl(addr.s_addr);
    return true;
}

// DIR-24-8 Longest-Prefix Match Search
inline bool search_lpm(uint32_t ip, string& asn, string& cc) {
    uint32_t tbl24_idx = ip >> 8;
    uint32_t entry = tbl24[tbl24_idx];
    uint32_t rec_idx;

    if ((entry & TBL8_FLAG) == 0) {
        rec_idx = entry;
    } else {
        uint32_t group_idx = entry & RECORD_MASK;
        uint8_t tbl8_offset = ip & 0xFF;
        rec_idx = tbl8_groups[group_idx][tbl8_offset];
    }

    if (rec_idx != 0) {
        asn = records[rec_idx].asn;
        cc = records[rec_idx].cc;
        return true;
    }
    return false;
}

double get_current_load() {
    double load[1];
    if (getloadavg(load, 1) != -1) {
        return load[0];
    }
    return 0.0;
}

int load_master_file(const string& filename) {
    ifstream file(filename);
    if (!file.is_open()) return -1;

    vector<MasterRecord> temp_records;
    string line;

    while (getline(file, line)) {
        if (!line.empty() && line.back() == '\r') line.pop_back();
        if (line.empty() || line[0] == '#') continue;

        auto pos1 = line.find(',');
        if (pos1 == string::npos) continue;
        auto pos2 = line.find(',', pos1 + 1);
        if (pos2 == string::npos) continue;

        string cidr = line.substr(0, pos1);
        string asn = line.substr(pos1 + 1, pos2 - pos1 - 1);
        string cc = line.substr(pos2 + 1);

        uint32_t ip;
        int prefix;
        if (parse_cidr(cidr, ip, prefix)) {
            temp_records.push_back({cidr, asn, cc, ip, prefix});
        }
    }

    sort(temp_records.begin(), temp_records.end(), [](const MasterRecord& a, const MasterRecord& b) {
        return a.prefix < b.prefix;
    });

    for (const auto& rec : temp_records) {
        records.push_back({rec.asn, rec.cc});
        uint32_t rec_idx = static_cast<uint32_t>(records.size() - 1);

        if (rec.prefix <= 24) {
            uint32_t start_idx = rec.ip >> 8;
            uint32_t num_entries = 1U << (24 - rec.prefix);
            uint32_t end_idx = start_idx + num_entries;

            for (uint32_t i = start_idx; i < end_idx; ++i) {
                tbl24[i] = rec_idx;
            }
        } else {
            uint32_t tbl24_idx = rec.ip >> 8;

            if ((tbl24[tbl24_idx] & TBL8_FLAG) == 0) {
                uint32_t existing_rec = tbl24[tbl24_idx];
                uint32_t new_group_idx = static_cast<uint32_t>(tbl8_groups.size());
                tbl8_groups.push_back(vector<uint32_t>(256, existing_rec));
                tbl24[tbl24_idx] = TBL8_FLAG | new_group_idx;
            }

            uint32_t group_idx = tbl24[tbl24_idx] & RECORD_MASK;
            uint32_t start_offset = rec.ip & 0xFF;
            uint32_t num_entries = 1U << (32 - rec.prefix);
            uint32_t end_offset = start_offset + num_entries;

            for (uint32_t off = start_offset; off < end_offset; ++off) {
                tbl8_groups[group_idx][off] = rec_idx;
            }
        }
    }

    return static_cast<int>(temp_records.size());
}

int load_filter_file(const string& filename, unordered_map<string, FilterAction>& map_dest) {
    ifstream file(filename);
    if (!file.is_open()) return -1;

    string line;
    int count = 0;
    while (getline(file, line)) {
        if (line.empty() || line[0] == '#') continue;

        auto pos = line.find('=');
        if (pos == string::npos) continue;

        string key = line.substr(0, pos);
        string val = line.substr(pos + 1);

        if (val == "allow") {
            map_dest[key] = ALLOW;
            count++;
        } else if (val == "filter") {
            map_dest[key] = FILTER;
            count++;
        }
    }
    return count;
}

void print_usage(const char* prog_name) {
    cerr << "Usage: " << prog_name << " [OPTIONS]\n"
         << "\n"
         << "Feed in IPv4 addresses w/ newline via stdin and this script will print to stdout the string\n"
         << "of either LIMIT or PASS. If an ASN/CC is set to filter, they will have LIMIT once the\n"
         << "trigger load set by '-f'; is hit. If an ASN/CC is set to allow, they will always have PASS\n"
         << "returned. If neither is set, then LIMIT will only be returned if load hits the number\n"
         << "specified by '-o'.\n"
         << "\n"
         << "Options:\n"
         << "  -m, --master <file>             Required. Master network CSV file (CIDR,ASN,CC).\n"
         << "  -c, --country <file>            Optional. Country code filter file (CC=allow|filter).\n"
         << "  -a, --asn <file>                Optional. ASN filter file (ASN=allow|filter).\n"
         << "  -f, --limit-filtered-at <num>   Required. Load avg threshold to trigger LIMIT on filtered IPs.\n"
         << "  -o, --limit-others-at <num>     Required. Load avg threshold to trigger LIMIT on other IPs.\n"
         << "  -r, --release-limit-level <num> Optional. Load avg must fall below trigger level by this\n"
         << "                                  amount in order to release that limit. Default 0.5.\n"
         << "  -s, --cache-size <num>          Optional. Max size for the IP response cache. Default 2000.\n"
         << "  -t, --cache-time <num>          Optional. Time in seconds to cache IP responses. Default 10.\n"
         << "  -v, --verbose                   Verbose output during loading.\n"
         << "  -d, --debug                     Debug output during IP lookups (implies -v).\n"
         << "  -h, --help                      Show this help message.\n";
}

/**
 * Checks if IP is in the cache already. If found and current, it resets that IP's cache lifetime.
 * @return Boolean if the IP was found and reset in the cache.
 */
inline bool check_and_refresh_cache(
    const string& ip,
    const chrono::steady_clock::time_point& now,
    int cache_time_sec,
    unordered_map<string,list<CacheEntry>::iterator>& cache_map,
    list<CacheEntry>& cache_list,
    string& result
) {
    auto it = cache_map.find(ip);
    if (it != cache_map.end() && it->second->expires_at > now) {
        it->second->expires_at = now + chrono::seconds(cache_time_sec);
        cache_list.splice(cache_list.end(), cache_list, it->second);
        result = it->second->result;
        return true;
    }
    return false;
}

/**
 * Update the known current load average and state whether filtered/others are limited
 * at that new load average.
 */
inline void refresh_load_and_limits(
    const chrono::steady_clock::time_point& now,
    chrono::steady_clock::time_point& last_load_time,
    double& current_load,
    double limit_filtered,
    double limit_others,
    double release_level,
    bool& is_filtered_limited,
    bool& is_others_limited
) {
    if (chrono::duration_cast<chrono::seconds>(now - last_load_time).count() >= 1) {
        current_load = get_current_load();
        last_load_time = now;
    }

    if (!is_filtered_limited && current_load >= limit_filtered) {
        is_filtered_limited = true;
    } else if (is_filtered_limited && current_load < max(0.1, limit_filtered - release_level)) {
        is_filtered_limited = false;
    }

    if (!is_others_limited && current_load >= limit_others) {
        is_others_limited = true;
    } else if (is_others_limited && current_load < max(0.1, limit_others - release_level)) {
        is_others_limited = false;
    }
}

/**
 * Given an IP, classify it to the appropriate IPClass.
 * @return An IPClass
 */
inline IPClass classify_input_ip(const string& input_ip, string& asn, string& cc, bool& found) {
    uint32_t ip;
    struct in_addr addr;
    if (inet_pton(AF_INET, input_ip.c_str(), &addr) != 1) {
        ip = 0; // Invalid IPv4
    } else {
        ip = ntohl(addr.s_addr);
    }

    found = search_lpm(ip, asn, cc);
    if (!found) return CLS_OTHER;

    FilterAction cc_action = cc_filters.count(cc) ? cc_filters[cc] : NONE;
    FilterAction asn_action = asn_filters.count(asn) ? asn_filters[asn] : NONE;

    if (asn_action == ALLOW) return CLS_ALLOW;
    if (asn_action == FILTER) return CLS_FILTER;
    if (cc_action == ALLOW) return CLS_ALLOW;
    if (cc_action == FILTER) return CLS_FILTER;
    return CLS_OTHER;
}

/**
 * Evaluate state to produce final output.
 * @return The PASS or LIMIT string.
 */
inline string evaluate_action(
    IPClass ip_class,
    bool is_filtered_limited,
    bool is_others_limited,
    string& class_str
) {
    if (ip_class == CLS_ALLOW) {
        class_str = "ALLOW";
        return "PASS";
    } else if (ip_class == CLS_FILTER) {
        class_str = "FILTER";
        return is_filtered_limited ? "LIMIT" : "PASS";
    } else {
        class_str = "OTHER";
        return is_others_limited ? "LIMIT" : "PASS";
    }
}

/**
 * Add an IP to the cache, or update an expired IP in the cache to be current again.
 */
inline void insert_or_update_cache(
    const string& input_ip,
    const string& result,
    const chrono::steady_clock::time_point& now,
    int cache_time_sec,
    size_t cache_size,
    unordered_map<string,list<CacheEntry>::iterator>& cache_map,
    list<CacheEntry>& cache_list
) {
    if (cache_size == 0) return;

    auto it = cache_map.find(input_ip);
    if (it != cache_map.end()) {
        it->second->result = result;
        it->second->expires_at = now + chrono::seconds(cache_time_sec);
        cache_list.splice(cache_list.end(), cache_list, it->second);
    } else {
        if (cache_map.size() >= cache_size) {
            cache_map.erase(cache_list.front().ip);
            cache_list.pop_front();
        }
        cache_list.push_back({input_ip, result, now + chrono::seconds(cache_time_sec)});
        cache_map[input_ip] = prev(cache_list.end());
    }
}


int main(int argc, char* argv[]) {
    string master_file, country_file, asn_file;
    double limit_filtered = -1.0, limit_others = -1.0;
    double release_level = 0.5;
    size_t cache_size = 2000;
    int cache_time_sec = 10;
    bool has_limit_filtered = false, has_limit_others = false;

    const char* const short_opts = "hm:c:a:f:o:r:s:t:vd";
    const option long_opts[] = {
        {"help", no_argument, nullptr, 'h'},
        {"master", required_argument, nullptr, 'm'},
        {"country", required_argument, nullptr, 'c'},
        {"asn", required_argument, nullptr, 'a'},
        {"limit-filtered-at", required_argument, nullptr, 'f'},
        {"limit-others-at", required_argument, nullptr, 'o'},
        {"release-limit-level", required_argument, nullptr, 'r'},
        {"cache-size", required_argument, nullptr, 's'},
        {"cache-time", required_argument, nullptr, 't'},
        {"verbose", no_argument, nullptr, 'v'},
        {"debug", no_argument, nullptr, 'd'},
        {nullptr, 0, nullptr, 0}
    };

    int opt;
    while ((opt = getopt_long(argc, argv, short_opts, long_opts, nullptr)) != -1) {
        switch (opt) {
            case 'm': master_file = optarg; break;
            case 'c': country_file = optarg; break;
            case 'a': asn_file = optarg; break;
            case 'f': limit_filtered = stod(optarg); has_limit_filtered = true; break;
            case 'o': limit_others = stod(optarg); has_limit_others = true; break;
            case 'r': release_level = stod(optarg); break;
            case 's': cache_size = stoull(optarg); break;
            case 't': cache_time_sec = stoi(optarg); break;
            case 'v': verbose = true; break;
            case 'd': debug_mode = true; break;
            case 'h': print_usage(argv[0]); return 0;
            default: print_usage(argv[0]); return 1;
        }
    }

    if (debug_mode) verbose = true;

    if (master_file.empty() || !has_limit_filtered || !has_limit_others) {
        cerr << "Error: --master, --limit-filtered-at, and --limit-others-at are required.\n";
        print_usage(argv[0]);
        return 1;
    }

    auto load_start = chrono::high_resolution_clock::now();

    int master_count = load_master_file(master_file);
    if (master_count < 0) {
        cerr << "Error: Failed to load master file: " << master_file << "\n";
        return 1;
    }

    int cc_count = 0;
    if (!country_file.empty()) {
        cc_count = load_filter_file(country_file, cc_filters);
        if (cc_count < 0) cerr << "Warning: Failed to load country file: " << country_file << "\n";
    }

    int asn_count = 0;
    if (!asn_file.empty()) {
        asn_count = load_filter_file(asn_file, asn_filters);
        if (asn_count < 0) cerr << "Warning: Failed to load ASN file: " << asn_file << "\n";
    }

    auto load_end = chrono::high_resolution_clock::now();

    if (verbose) {
        auto duration = chrono::duration_cast<chrono::milliseconds>(load_end - load_start).count();
        cerr << "[VERBOSE] Loaded " << master_count << " master records, "
             << cc_count << " country filters, and " << asn_count
             << " ASN filters in " << duration << " ms.\n";
        cerr << "[VERBOSE] DIR-24-8 table built using " << tbl8_groups.size() << " secondary tbl8 groups.\n";
        cerr << "[VERBOSE] Limit Filtered: " << limit_filtered
             << " (Releases at: " << max(0.1, limit_filtered - release_level) << ")\n";
        cerr << "[VERBOSE] Limit Others: " << limit_others
             << " (Releases at: " << max(0.1, limit_others - release_level) << ")\n";
        cerr << "[VERBOSE] Cache Size: " << cache_size << " | Cache TTL: " << cache_time_sec << "s\n";
        cerr << "[VERBOSE] Ready to process IPs on stdin.\n";
    }

    list<CacheEntry> cache_list;
    unordered_map<string, list<CacheEntry>::iterator> cache_map;
    cache_map.reserve(cache_size);

    string input_ip;
    double current_load = get_current_load();
    auto last_load_time = chrono::steady_clock::now();

    bool is_filtered_limited = false;
    bool is_others_limited = false;

    ios_base::sync_with_stdio(false);
    cin.tie(NULL);

    while (getline(cin, input_ip)) {
        if (input_ip.empty()) continue;

        auto proc_start = chrono::high_resolution_clock::now();
        auto now = chrono::steady_clock::now();
        string result;

        if (check_and_refresh_cache(input_ip, now, cache_time_sec, cache_map, cache_list, result)) {
            cout << result << "\n";
            cout << flush;

            if (debug_mode) {
                auto duration = chrono::duration_cast<chrono::microseconds>(
                    chrono::high_resolution_clock::now() - proc_start
                ).count();
                cerr << "[DEBUG] IP: " << input_ip << " | CACHE HIT | Output: " << result << " | Time: " << duration << " us\n";
                cerr << flush;
            }
            continue;
        }

        refresh_load_and_limits(now, last_load_time, current_load, limit_filtered, limit_others, release_level, is_filtered_limited, is_others_limited);

        string asn, cc;
        bool found = false;
        IPClass ip_class = classify_input_ip(input_ip, asn, cc, found);

        string class_str;
        result = evaluate_action(ip_class, is_filtered_limited, is_others_limited, class_str);

        insert_or_update_cache(input_ip, result, now, cache_time_sec, cache_size, cache_map, cache_list);

        cout << result << "\n";
        cout << flush;

        if (debug_mode) {
            auto duration = chrono::duration_cast<chrono::microseconds>(
                chrono::high_resolution_clock::now() - proc_start
            ).count();
            cerr << "[DEBUG] IP: " << input_ip
                 << " | Match: " << (found ? (asn + "/" + cc) : "None")
                 << " | Class: " << class_str
                 << " | Load: " << current_load
                 << " | Output: " << result
                 << " | Time: " << duration << " us\n";
            cerr << flush;
        }
    }

    return 0;
}
