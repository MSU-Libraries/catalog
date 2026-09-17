#include <iostream>
#include <vector>
#include <algorithm>
#include <cstdint>
#include <cstdio>
#include <cstring>
#include <chrono>
#include <sys/mman.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

const int MAX_BLOCK_SIZE = 8'000'000;
const int MAX_ALLOW_SIZE = 1'000'000;

struct Interval {
    uint32_t start;
    uint32_t end;
};

// Global flags
bool verbose = false;
bool debug = false;

// Fast custom parser for CIDR lines (IP or IP/CIDR)
inline bool parse_line(const char* str, size_t len, uint32_t& start, uint32_t& end) {
    uint32_t ip = 0, val = 0;
    int dots = 0;
    size_t i = 0;

    for (; i < len; ++i) {
        char c = str[i];
        if (c >= '0' && c <= '9') {
            val = val * 10 + (c - '0');
        } else if (c == '.') {
            if (val > 255 || dots >= 3) return false;
            ip = (ip << 8) | val;
            val = 0;
            dots++;
        } else if (c == '/' || c == '\n' || c == '\r' || c == ' ' || c == '\t') {
            break;
        } else {
            return false;
        }
    }
    if (dots != 3 || val > 255) return false;
    ip = (ip << 8) | val;

    uint32_t mask_len = 32;
    if (i < len && str[i] == '/') {
        i++;
        val = 0;
        bool has_mask = false;
        for (; i < len; ++i) {
            char c = str[i];
            if (c >= '0' && c <= '9') {
                val = val * 10 + (c - '0');
                has_mask = true;
            } else {
                break;
            }
        }
        if (!has_mask) return false;
        mask_len = val;
    }

    if (mask_len > 32) return false;

    if (mask_len == 0) {
        start = 0;
        end = 0xFFFFFFFF;
    } else {
        uint32_t mask = ~0U << (32 - mask_len);
        start = ip & mask;
        end = start | ~mask;
    }
    return true;
}

// Fast parser for incoming query IP addresses from Apache
inline bool parse_ip(const char* str, uint32_t& ip) {
    uint32_t result = 0, val = 0;
    int dots = 0;
    for (size_t i = 0; str[i] != '\0' && str[i] != '\n' && str[i] != '\r' && str[i] != ' '; ++i) {
        char c = str[i];
        if (c >= '0' && c <= '9') {
            val = val * 10 + (c - '0');
        } else if (c == '.') {
            if (val > 255 || dots >= 3) return false;
            result = (result << 8) | val;
            val = 0;
            dots++;
        } else {
            return false;
        }
    }
    if (dots != 3 || val > 255) return false;
    ip = (result << 8) | val;
    return true;
}

// Memory-map multiple files, parse intervals, ignore comments, sort, and merge
std::vector<Interval> load_and_merge_cidrs(const std::vector<const char*>& filepaths, const char* list_name, const int max_size) {
    if (filepaths.empty()) return {};

    auto start_time = std::chrono::high_resolution_clock::now();
    std::vector<Interval> raw;

    // Pre-allocate memory assuming large files to prevent expensive reallocations
    raw.reserve(max_size * filepaths.size());

    for (const char* filepath : filepaths) {
        if (verbose) {
            std::cerr << "[INFO] Opening " << list_name << " file: " << filepath << std::endl;
        }

        int fd = open(filepath, O_RDONLY);
        if (fd == -1) {
            std::cerr << "[ERROR] Could not open file: " << filepath << std::endl;
            exit(1);
        }

        struct stat sb;
        if (fstat(fd, &sb) == -1) {
            std::cerr << "[ERROR] Could not stat file: " << filepath << std::endl;
            exit(1);
        }

        size_t filesize = sb.st_size;
        if (filesize == 0) {
            if (verbose) std::cerr << "[WARN] File is empty: " << filepath << std::endl;
            close(fd);
            continue;
        }

        char* data = static_cast<char*>(mmap(NULL, filesize, PROT_READ, MAP_PRIVATE, fd, 0));
        if (data == MAP_FAILED) {
            std::cerr << "[ERROR] Memory mapping failed for: " << filepath << std::endl;
            exit(1);
        }

        if (madvise(data, filesize, MADV_SEQUENTIAL) != 0 && verbose) {
            std::cerr << "[INFO] madvise failed, falling back to standard OS paging." << std::endl;
        }

        size_t start_pos = 0;
        for (size_t i = 0; i <= filesize; ++i) {
            if (i == filesize || data[i] == '\n') {
                if (i > start_pos) {
                    // Skip any leading whitespace to accurately find comments
                    size_t line_start = start_pos;
                    while (line_start < i && (data[line_start] == ' ' || data[line_start] == '\t')) {
                        line_start++;
                    }

                    // Process if the line isn't empty and doesn't start with '#'
                    if (line_start < i && data[line_start] != '#') {
                        uint32_t s, e;
                        if (parse_line(data + line_start, i - line_start, s, e)) {
                            if (raw.size() == max_size) {
                                std::cerr << "[WARN] Exceeded max entries (" << max_size << ") for " << list_name << "; skipping remainder of " << filepath << std::endl;
                                break;
                            }
                            raw.push_back({s, e});
                        }
                    }
                }
                start_pos = i + 1;
            }
        }

        munmap(data, filesize);
        close(fd);
    }

    if (verbose) {
        std::cerr << "[INFO] Parsed " << raw.size() << " total raw entries for " << list_name << ". Sorting..." << std::endl;
    }

    // Sort by start address
    std::sort(raw.begin(), raw.end(), [](const Interval& a, const Interval& b) {
        if (a.start != b.start) return a.start < b.start;
        return a.end < b.end;
    });

    if (verbose) {
        std::cerr << "[INFO] Merging overlapping ranges for " << list_name << "..." << std::endl;
    }

    // Merge overlapping and adjacent ranges
    std::vector<Interval> merged;
    merged.reserve(raw.size());

    for (const auto& curr : raw) {
        if (merged.empty()) {
            merged.push_back(curr);
        } else {
            auto& last = merged.back();
            if (curr.start <= last.end || (last.end < 0xFFFFFFFF && curr.start == last.end + 1)) {
                if (curr.end > last.end) last.end = curr.end;
            } else {
                merged.push_back(curr);
            }
        }
    }

    auto end_time = std::chrono::high_resolution_clock::now();
    std::chrono::duration<double> diff = end_time - start_time;

    if (verbose) {
        std::cerr << "[INFO] " << list_name << " list merged into " << merged.size() << " disjoint intervals." << std::endl;
        std::cerr << "[INFO] Processing time for " << list_name << ": " << diff.count() << " seconds." << std::endl;
    }

    return merged;
}

// Binary search through sorted intervals
inline bool contains_ip(const std::vector<Interval>& intervals, uint32_t ip) {
    if (intervals.empty()) return false;
    auto it = std::lower_bound(intervals.begin(), intervals.end(), ip,
        [](const Interval& inv, uint32_t target) {
            return inv.end < target;
        });
    return (it != intervals.end() && ip >= it->start);
}

void print_help(const char* prog_name) {
    std::cout << "Usage: " << prog_name << " [-h] [-v] [-a <allow_file>] <cidr_file1> [cidr_file2 ...]\n\n"
              << "Arguments:\n"
              << "  -h                  Show this help message and exit\n"
              << "  -v                  Enable verbose logging (outputs to stderr)\n"
              << "  --debug             Enable debug logging (outputs to stderr)\n"
              << "  -a <allow_file>     Optional path to a file containing IPs/CIDRs to never proxy\n"
              << "  <cidr_file>         One or more paths to text files containing IPs/CIDRs to proxy\n\n"
              << "Note: Files can contain comments starting with '#'.\n";
}

int main(int argc, char* argv[]) {
    std::vector<const char*> proxy_filepaths;
    const char* allow_filepath = nullptr;

    // Parse command line arguments
    for (int i = 1; i < argc; ++i) {
        if (strcmp(argv[i], "-h") == 0 || strcmp(argv[i], "--help") == 0) {
            print_help(argv[0]);
            return 0;
        } else if (strcmp(argv[i], "-v") == 0) {
            verbose = true;
        } else if (strcmp(argv[i], "--debug") == 0) {
            verbose = true;
            debug = true;
        } else if (strcmp(argv[i], "-a") == 0) {
            if (i + 1 < argc) {
                allow_filepath = argv[++i];
            } else {
                std::cerr << "[ERROR] Missing file path after -a flag.\n\n";
                print_help(argv[0]);
                return 1;
            }
        } else if (argv[i][0] == '-') {
            std::cerr << "[ERROR] Unknown flag: " << argv[i] << "\n\n";
            print_help(argv[0]);
            return 1;
        } else {
            proxy_filepaths.push_back(argv[i]);
        }
    }

    if (proxy_filepaths.empty()) {
        std::cerr << "[ERROR] Missing CIDR file path. At least one proxy file is required.\n\n";
        print_help(argv[0]);
        return 1;
    }

    // Load allow (never proxy) list if provided
    std::vector<Interval> allow_intervals;
    if (allow_filepath != nullptr) {
        std::vector<const char*> allow_files = {allow_filepath};
        allow_intervals = load_and_merge_cidrs(allow_files, "ALLOW", MAX_ALLOW_SIZE);
    }

    // Load proxy lists
    auto proxy_intervals = load_and_merge_cidrs(proxy_filepaths, "PROXY", MAX_BLOCK_SIZE);

    if (verbose) {
        std::cerr << "[INFO] All files loaded. Ready and waiting for input on stdin..." << std::endl;
    }

    // Apache IPC Loop
    char buf[256];
    std::chrono::time_point<std::chrono::high_resolution_clock> start_time;
    while (fgets(buf, sizeof(buf), stdin)) {
        // Strip newline for clean logging
        size_t len = strlen(buf);
        if (len > 0 && buf[len-1] == '\n') buf[len-1] = '\0';
        if (len > 1 && buf[len-2] == '\r') buf[len-2] = '\0';

        if (buf[0] == '\0') continue; // Skip empty lines

        if (debug) {
            start_time = std::chrono::high_resolution_clock::now();
        }

        uint32_t ip;
        bool match = false;

        if (parse_ip(buf, ip)) {
            if (contains_ip(allow_intervals, ip)) {
                // If it's in the allowlist, definitively do NOT proxy
                match = false;
            } else if (contains_ip(proxy_intervals, ip)) {
                // If it's not in the allowlist but IS in the proxylist, PROXY
                match = true;
            }
        }

        if (debug) {
            auto end_time = std::chrono::high_resolution_clock::now();
            std::chrono::duration<double> diff = end_time - start_time;
            std::cerr << "[DEBUG] Query: " << buf << " -> " << (match ? "PROXY" : "NULL") << " (" << diff.count() << " seconds)" << std::endl;
        }

        if (match) {
            fputs("PROXY\n", stdout);
        } else {
            fputs("NULL\n", stdout);
        }

        fflush(stdout); // Mandatory for Apache RewriteMap
    }

    return 0;
}
